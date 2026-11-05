<?php

namespace App\Console\Commands;

use App\Models\BncNotification;
use App\Models\BncPaymentRequest;
use App\Services\Bnc\BncPaymentRequestService;
use App\Services\Bnc\BncPaymentVerifier;
use Illuminate\Console\Command;
use Throwable;

class BncReconcilePayments extends Command
{
    protected $signature = 'bnc:reconcile-payments
        {--limit=100 : Máximo de solicitudes pendientes a revisar}
        {--notifications=100 : Máximo de notificaciones recibidas a procesar}';

    protected $description = 'Reintenta verificar pagos BNC pendientes y procesa notificaciones sin conciliar.';

    public function handle(
        BncPaymentVerifier $verifier,
        BncPaymentRequestService $requests
    ): int {
        if (! config('services.bnc.enabled')) {
            $this->warn('BNC_ENABLED=false. No se ejecuta la conciliación.');

            return self::SUCCESS;
        }

        $stats = [
            'checked' => 0,
            'verified' => 0,
            'rejected' => 0,
            'pending' => 0,
            'notifications' => 0,
            'unmatched' => 0,
            'expired' => 0,
            'errors' => 0,
        ];

        $pending = BncPaymentRequest::whereIn('status', [
            BncPaymentRequest::STATUS_PENDING,
            BncPaymentRequest::STATUS_ERROR,
        ])
            ->orderBy('created_at')
            ->limit(max(1, (int) $this->option('limit')))
            ->get();

        foreach ($pending as $request) {
            $stats['checked']++;

            try {
                $result = $verifier->verify($request);

                match ($result->status) {
                    BncPaymentRequest::STATUS_VERIFIED => $stats['verified']++,
                    BncPaymentRequest::STATUS_REJECTED => $stats['rejected']++,
                    BncPaymentRequest::STATUS_EXPIRED => $stats['expired']++,
                    default => $stats['pending']++,
                };
            } catch (Throwable $e) {
                $stats['errors']++;
                $this->warn("Solicitud #{$request->id}: {$e->getMessage()}");
            }
        }

        $notifications = BncNotification::whereIn('status', [
            BncNotification::STATUS_RECEIVED,
            BncNotification::STATUS_ERROR,
        ])
            ->where('created_at', '<=', now()->subMinute())
            ->orderBy('created_at')
            ->limit(max(1, (int) $this->option('notifications')))
            ->get();

        foreach ($notifications as $notification) {
            $stats['notifications']++;

            try {
                $result = $verifier->verifyFromNotification($notification);

                if ($result->status === BncNotification::STATUS_UNMATCHED) {
                    $stats['unmatched']++;
                }
            } catch (Throwable $e) {
                $stats['errors']++;
                $this->warn("Notificación #{$notification->id}: {$e->getMessage()}");
            }
        }

        $stats['expired'] += $requests->expireOld();

        $this->table(['Métrica', 'Valor'], [
            ['Solicitudes revisadas', $stats['checked']],
            ['Verificadas', $stats['verified']],
            ['Rechazadas', $stats['rejected']],
            ['Siguen pendientes', $stats['pending']],
            ['Expiradas', $stats['expired']],
            ['Notificaciones procesadas', $stats['notifications']],
            ['Sin conciliar', $stats['unmatched']],
            ['Errores', $stats['errors']],
        ]);

        return self::SUCCESS;
    }
}
