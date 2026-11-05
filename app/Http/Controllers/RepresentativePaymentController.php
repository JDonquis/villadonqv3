<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethodEnum;
use App\Http\Requests\StoreChargePaymentRequest;
use App\Http\Requests\StorePaymentRequest;
use App\Models\AccountPayment;
use App\Models\BalanceStudent;
use App\Models\BncPaymentRequest;
use App\Models\MainConfig;
use App\Models\PaymentConcept;
use App\Services\Bnc\BncClient;
use App\Services\Bnc\BncPaymentRequestService;
use App\Services\Bnc\BncPaymentVerifier;
use App\Services\MainConfigService;
use App\Services\PaymentService;
use App\Services\RepresentativeService;
use App\Support\ErrorTranslator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Throwable;

class RepresentativePaymentController extends Controller
{
    private MainConfigService $mainConfigService;

    private PaymentService $paymentService;

    private RepresentativeService $representativeService;

    private BncPaymentRequestService $bncRequestService;

    private BncPaymentVerifier $bncVerifier;

    public function __construct()
    {
        $this->mainConfigService = new MainConfigService;
        $this->paymentService = new PaymentService;
        $this->representativeService = new RepresentativeService;
        $this->bncRequestService = new BncPaymentRequestService;
        $this->bncVerifier = new BncPaymentVerifier;
    }

    public function index(Request $request): Response
    {
        $user = Auth::user();
        $allowedStudentIds = $this->representativeService->getStudents($user)->pluck('id')->all();

        $prices = $this->mainConfigService->getPrices();
        $accounts = $this->mainConfigService->getAccountsByMethods([
            PaymentMethodEnum::PagoMovil->value,
            PaymentMethodEnum::Transferencia->value,
        ]);
        $result = $this->paymentService->getAll($request->all(), $allowedStudentIds);
        $config = MainConfig::select('day_of_monthly_payment', 'grace_period', 'ame_price', 'investment_plan_price')->first();

        return inertia('Dashboard/MisPagos', [
            'data' => [
                'students' => $this->representativeService->misPagos($user)['students'],
                'accounts' => $accounts,
                'payments' => $result['payments'],
                'prices' => $prices,
                'total_income' => $result['total_income'],
                'concepts' => PaymentConcept::active()->orderBy('name')->get(),
                'pending_verifications' => $this->bncRequestService->pendingForUser($user->id),
                'bnc' => [
                    'enabled' => (bool) config('services.bnc.enabled'),
                    'configured' => (new BncClient)->isConfigured(),
                    'require_reference' => (bool) config('services.bnc.require_reference', true),
                ],
            ],
            'config' => $config,
        ]);
    }

    public function store(StorePaymentRequest $request)
    {
        $user = Auth::user();
        $allowedStudentIds = $this->representativeService->getStudents($user)->pluck('id')->all();

        $validated = $request->validated();

        $this->assertStudentsOwnership($validated['students'], $allowedStudentIds);
        $this->assertBalancesOwnership($validated['students']);

        $account = AccountPayment::with('method')->findOrFail($validated['account_payment_id']);

        if (config('services.bnc.verify_on_report', true) && $this->bncVerifier->isEligible($account)) {
            return $this->storeWithBnc($validated, $account, $user, $allowedStudentIds, 'regular');
        }

        DB::beginTransaction();

        try {
            $this->paymentService->create($validated, $allowedStudentIds);

            DB::commit();

            return redirect('/dashboard/mis-pagos')->with('payment_flash', [
                'status' => 'success',
                'message' => 'Pago registrado correctamente.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error al crear pago (representante): '.$e->getMessage());

            return redirect('/dashboard/mis-pagos')->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function storeCharges(StoreChargePaymentRequest $request)
    {
        $user = Auth::user();
        $allowedStudentIds = $this->representativeService->getStudents($user)->pluck('id')->all();

        $validated = $request->validated();

        $submittedStudentIds = collect($validated['items'])
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($submittedStudentIds->diff($allowedStudentIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'No puedes registrar pagos para estudiantes que no te pertenecen.',
            ]);
        }

        $account = AccountPayment::with('method')->findOrFail($validated['account_payment_id']);

        if (config('services.bnc.verify_on_report', true) && $this->bncVerifier->isEligible($account)) {
            return $this->storeWithBnc($validated, $account, $user, $allowedStudentIds, 'charges');
        }

        DB::beginTransaction();

        try {
            $this->paymentService->createForStudentCharges($validated, $allowedStudentIds);

            DB::commit();

            return redirect('/dashboard/mis-pagos')->with('payment_flash', [
                'status' => 'success',
                'message' => 'Pago de conceptos registrado correctamente.',
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Error al crear pago de conceptos (representante): '.$e->getMessage());

            return redirect('/dashboard/mis-pagos')->withErrors(['message' => ErrorTranslator::translate($e)]);
        }
    }

    public function verifyPending(Request $request, int $id)
    {
        $user = Auth::user();
        $bncRequest = BncPaymentRequest::findOrFail($id);

        try {
            $bncRequest = $this->bncRequestService->verifyForUser($bncRequest, $user);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Error verificando pago BNC pendiente '.$id.': '.$e->getMessage());

            return back()->with('payment_flash', [
                'status' => 'error',
                'message' => 'No se pudo verificar el pago: '.$e->getMessage(),
            ]);
        }

        return back()->with('payment_flash', $this->flashFor($bncRequest));
    }

    private function storeWithBnc(array $validated, AccountPayment $account, $user, array $allowedStudentIds, string $type)
    {
        DB::beginTransaction();

        try {
            $bncRequest = $this->bncRequestService->create($validated, $account, $user, $allowedStudentIds, $type);
            $bncRequest = $this->bncVerifier->verify($bncRequest);

            DB::commit();
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error al registrar/verificar pago BNC (representante): '.$e->getMessage());

            return redirect('/dashboard/mis-pagos')->with('payment_flash', [
                'status' => 'error',
                'message' => 'No se pudo registrar el pago: '.$e->getMessage(),
            ]);
        }

        return redirect('/dashboard/mis-pagos')->with('payment_flash', $this->flashFor($bncRequest));
    }

    private function flashFor(BncPaymentRequest $request): array
    {
        return match ($request->status) {
            BncPaymentRequest::STATUS_VERIFIED => [
                'status' => 'success',
                'message' => 'Pago verificado con el BNC y registrado correctamente.',
            ],
            BncPaymentRequest::STATUS_REJECTED, BncPaymentRequest::STATUS_EXPIRED => [
                'status' => 'error',
                'message' => $request->last_error ?: 'No se pudo verificar el pago con el BNC.',
            ],
            default => [
                'status' => 'info',
                'message' => $request->last_error
                    ? 'Pago registrado. Quedó pendiente de verificación: '.$request->last_error
                    : 'Pago registrado. Está pendiente de verificación automática con el BNC.',
            ],
        };
    }

    private function assertStudentsOwnership(array $students, array $allowedStudentIds): void
    {
        $submittedStudentIds = collect($students)
            ->pluck('id')
            ->map(fn ($id) => (int) $id);

        if ($submittedStudentIds->diff($allowedStudentIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'students' => 'No puedes registrar pagos para estudiantes que no te pertenecen.',
            ]);
        }
    }

    private function assertBalancesOwnership(array $students): void
    {
        foreach ($students as $studentData) {
            $balanceIds = collect($studentData['balances'] ?? [])
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

            if ($balanceIds->isEmpty()) {
                continue;
            }

            $ownedBalanceIds = BalanceStudent::where('student_id', $studentData['id'])
                ->whereIn('id', $balanceIds)
                ->pluck('id');

            if ($ownedBalanceIds->count() !== $balanceIds->count()) {
                throw ValidationException::withMessages([
                    'students' => 'Uno o más balances no pertenecen al estudiante seleccionado.',
                ]);
            }
        }
    }
}
