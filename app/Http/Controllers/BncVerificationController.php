<?php

namespace App\Http\Controllers;

use App\Models\BncNotification;
use App\Models\BncPaymentRequest;
use App\Models\MainConfig;
use App\Models\Student;
use App\Services\Bnc\BncClient;
use App\Services\Bnc\BncPaymentRequestService;
use App\Services\Bnc\BncPaymentVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;
use Throwable;

class BncVerificationController extends Controller
{
    public function __construct(
        private BncPaymentRequestService $requests = new BncPaymentRequestService,
        private BncPaymentVerifier $verifier = new BncPaymentVerifier
    ) {}

    public function index(Request $request): Response
    {
        $status = (string) $request->input('status', 'pending');
        $notificationStatus = (string) $request->input('notification_status', 'unmatched');
        $search = trim((string) $request->input('search'));

        $requests = BncPaymentRequest::with(['user', 'accountPayment.method'])
            ->when($status !== 'all', function ($query) use ($status) {
                if ($status === 'pending') {
                    $query->whereIn('status', [
                        BncPaymentRequest::STATUS_PENDING,
                        BncPaymentRequest::STATUS_ERROR,
                    ]);
                } else {
                    $query->where('status', $status);
                }
            })
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('reference', 'like', '%'.$search.'%')
                        ->orWhere('last_error', 'like', '%'.$search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('ci', 'like', '%'.$search.'%');
                        });
                });
            })
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        $studentIds = collect($requests->items())
            ->flatMap(fn (BncPaymentRequest $row) => $row->studentIds())
            ->unique()
            ->values();

        $students = $studentIds->isEmpty()
            ? collect()
            : Student::whereIn('id', $studentIds)->get(['id', 'name', 'last_name'])->keyBy('id');

        $requests->through(function (BncPaymentRequest $row) use ($students) {
            $ids = $row->studentIds();

            return [
                'id' => $row->id,
                'reference' => $row->reference,
                'reference_normalized' => $row->reference_normalized,
                'amount' => (float) $row->amount,
                'amount_usd' => (float) $row->amount_usd,
                'amount_ves' => (float) $row->amount_ves,
                'currency' => $row->currency,
                'transaction_date' => $row->transaction_date?->toDateString(),
                'status' => $row->status,
                'status_label' => $row->statusLabel(),
                'type' => $row->type,
                'attempts' => $row->attempts,
                'last_error' => $row->last_error,
                'last_checked_at' => $row->last_checked_at?->toIso8601String(),
                'verified_at' => $row->verified_at?->toIso8601String(),
                'created_at' => $row->created_at?->toIso8601String(),
                'verification_source' => $row->verification_source,
                'payment_id' => $row->payment_id,
                'user' => [
                    'id' => $row->user?->id,
                    'name' => $row->user?->name,
                    'last_name' => $row->user?->last_name,
                    'ci' => $row->user?->ci,
                ],
                'account' => [
                    'id' => $row->accountPayment?->id,
                    'method' => $row->accountPayment?->method?->name,
                    'bank' => $row->accountPayment?->bank,
                    'account_number' => $row->accountPayment?->account_number,
                ],
                'students' => collect($ids)
                    ->map(function ($id) use ($students) {
                        $student = $students->get($id);

                        return $student ? trim($student->name.' '.$student->last_name) : null;
                    })
                    ->filter()
                    ->values()
                    ->all(),
            ];
        });

        $notifications = BncNotification::when($notificationStatus !== 'all', fn ($query) => $query->where('status', $notificationStatus))
            ->latest('id')
            ->paginate(15, ['*'], 'notification_page')
            ->withQueryString();

        $notifications->through(fn (BncNotification $row) => [
            'id' => $row->id,
            'payment_type' => $row->payment_type,
            'origin_bank_reference' => $row->origin_bank_reference,
            'destiny_bank_reference' => $row->destiny_bank_reference,
            'amount' => (float) $row->amount,
            'currency_code' => $row->currency_code,
            'transaction_date' => $row->transaction_date?->toDateString(),
            'status' => $row->status,
            'status_label' => $row->statusLabel(),
            'error' => $row->error,
            'payment_id' => $row->payment_id,
            'request_id' => $row->bnc_payment_request_id,
            'created_at' => $row->created_at?->toIso8601String(),
            'raw_payload' => $row->raw_payload,
        ]);

        $client = new BncClient;
        $mainRif = MainConfig::first()?->rif;
        $accountNumber = (string) config('services.bnc.account_number');

        return inertia('Dashboard/BncVerificaciones', [
            'data' => [
                'requests' => $requests,
                'notifications' => $notifications,
                'filters' => [
                    'status' => $status,
                    'notification_status' => $notificationStatus,
                    'search' => $search,
                ],
                'stats' => [
                    'pending' => $this->requests->pendingCount(),
                    'verified_today' => BncPaymentRequest::where('status', BncPaymentRequest::STATUS_VERIFIED)
                        ->whereDate('verified_at', today())
                        ->count(),
                    'rejected' => BncPaymentRequest::where('status', BncPaymentRequest::STATUS_REJECTED)->count(),
                    'expired' => BncPaymentRequest::where('status', BncPaymentRequest::STATUS_EXPIRED)->count(),
                    'unmatched_notifications' => BncNotification::where('status', BncNotification::STATUS_UNMATCHED)->count(),
                    'received_notifications' => BncNotification::whereIn('status', [
                        BncNotification::STATUS_RECEIVED,
                        BncNotification::STATUS_ERROR,
                    ])->count(),
                ],
                'config' => [
                    'enabled' => (bool) config('services.bnc.enabled'),
                    'configured' => $client->isConfigured(),
                    'has_working_key' => $client->hasCachedWorkingKey(),
                    'last_logon_at' => $client->lastLogOnAt(),
                    'base_url' => config('services.bnc.base_url'),
                    'webhook_url' => url('/api/bnc/notifications'),
                    'webhook_auth' => config('services.bnc.webhook.auth', 'api_key'),
                    'require_reference' => (bool) config('services.bnc.require_reference', true),
                    'client_guid' => $this->mask((string) config('services.bnc.client_guid')),
                    'client_id' => config('services.bnc.client_id') ?: $mainRif,
                    'account_number' => $this->mask($accountNumber),
                    'mode' => config('services.bnc.verification_mode', 'auto'),
                    'missing' => collect([
                        'BNC_CLIENT_GUID' => filled(config('services.bnc.client_guid')),
                        'BNC_MASTER_KEY' => filled(config('services.bnc.master_key')),
                        'BNC_CLIENT_ID o RIF del plantel' => filled(config('services.bnc.client_id') ?: $mainRif),
                        'BNC_ACCOUNT_NUMBER o cuenta de la cuenta' => filled($accountNumber),
                    ])->filter(fn ($ok) => ! $ok)->keys()->values()->all(),
                ],
            ],
        ]);
    }

    public function verify(Request $request, int $id)
    {
        $bncRequest = BncPaymentRequest::findOrFail($id);
        $result = $this->requests->verify($bncRequest, true);

        return back()->with('payment_flash', $this->flashFor($result));
    }

    public function verifyAll()
    {
        $rows = BncPaymentRequest::whereIn('status', [
            BncPaymentRequest::STATUS_PENDING,
            BncPaymentRequest::STATUS_ERROR,
        ])->orderBy('created_at')->limit(50)->get();

        $counts = ['verified' => 0, 'pending' => 0, 'rejected' => 0, 'expired' => 0, 'error' => 0];

        foreach ($rows as $row) {
            try {
                $result = $this->requests->verify($row, true);

                match ($result->status) {
                    BncPaymentRequest::STATUS_VERIFIED => $counts['verified']++,
                    BncPaymentRequest::STATUS_REJECTED => $counts['rejected']++,
                    BncPaymentRequest::STATUS_EXPIRED => $counts['expired']++,
                    default => $counts['pending']++,
                };
            } catch (Throwable) {
                $counts['error']++;
            }
        }

        return back()->with('payment_flash', [
            'status' => 'info',
            'message' => sprintf(
                'Revisadas %d: %d verificadas, %d pendientes, %d rechazadas, %d expiradas, %d con error.',
                $rows->count(),
                $counts['verified'],
                $counts['pending'],
                $counts['rejected'],
                $counts['expired'],
                $counts['error']
            ),
        ]);
    }

    public function reject(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $bncRequest = BncPaymentRequest::findOrFail($id);
        $this->verifier->reject($bncRequest, $validated['reason']);

        return back()->with('payment_flash', [
            'status' => 'warning',
            'message' => 'Solicitud rechazada y representante notificado.',
        ]);
    }

    public function verifyManual(Request $request, int $id)
    {
        $validated = $request->validate([
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $bncRequest = BncPaymentRequest::findOrFail($id);
        $this->verifier->verifyManually($bncRequest, $validated['note'] ?? null);

        return back()->with('payment_flash', [
            'status' => 'success',
            'message' => 'Pago verificado manualmente y registrado.',
        ]);
    }

    public function processNotifications()
    {
        $rows = BncNotification::whereIn('status', [
            BncNotification::STATUS_RECEIVED,
            BncNotification::STATUS_ERROR,
            BncNotification::STATUS_UNMATCHED,
        ])->latest('id')->limit(50)->get();

        $processed = 0;
        $unmatched = 0;
        $errors = 0;

        foreach ($rows as $row) {
            try {
                $result = $this->verifier->verifyFromNotification($row);

                if ($result->status === BncNotification::STATUS_UNMATCHED) {
                    $unmatched++;
                } else {
                    $processed++;
                }
            } catch (Throwable) {
                $errors++;
            }
        }

        return back()->with('payment_flash', [
            'status' => 'info',
            'message' => "Notificaciones revisadas: {$rows->count()} (procesadas {$processed}, sin conciliar {$unmatched}, errores {$errors}).",
        ]);
    }

    public function testConnection(): JsonResponse
    {
        $client = new BncClient;

        if (! $client->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'Faltan BNC_CLIENT_GUID y/o BNC_MASTER_KEY.',
            ], 422);
        }

        try {
            return response()->json($client->testConnection());
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    private function flashFor(BncPaymentRequest $request): array
    {
        return match ($request->status) {
            BncPaymentRequest::STATUS_VERIFIED => [
                'status' => 'success',
                'message' => 'Pago verificado y registrado correctamente.',
            ],
            BncPaymentRequest::STATUS_REJECTED, BncPaymentRequest::STATUS_EXPIRED => [
                'status' => 'error',
                'message' => $request->last_error ?: 'La solicitud fue rechazada o expiró.',
            ],
            default => [
                'status' => 'info',
                'message' => $request->last_error
                    ? 'Sigue pendiente: '.$request->last_error
                    : 'El pago continúa pendiente de verificación.',
            ],
        };
    }

    private function mask(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        if (strlen($value) <= 8) {
            return str_repeat('*', strlen($value));
        }

        return substr($value, 0, 4).str_repeat('*', max(4, strlen($value) - 8)).substr($value, -4);
    }
}
