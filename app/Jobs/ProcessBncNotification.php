<?php

namespace App\Jobs;

use App\Models\BncNotification;
use App\Services\Bnc\BncPaymentVerifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBncNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $notificationId) {}

    public function handle(BncPaymentVerifier $verifier): void
    {
        $notification = BncNotification::find($this->notificationId);

        if (! $notification) {
            return;
        }

        if (in_array($notification->status, [
            BncNotification::STATUS_PROCESSED,
            BncNotification::STATUS_DUPLICATE,
        ], true)) {
            return;
        }

        $notification->update(['status' => BncNotification::STATUS_PROCESSING]);

        try {
            $verifier->verifyFromNotification($notification);
        } catch (Throwable $e) {
            $notification->update([
                'status' => BncNotification::STATUS_ERROR,
                'error' => $e->getMessage(),
                'processed_at' => now(),
            ]);

            Log::error('Error procesando notificación BNC '.$notification->id.': '.$e->getMessage());

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        BncNotification::whereKey($this->notificationId)->update([
            'status' => BncNotification::STATUS_ERROR,
            'error' => $e->getMessage(),
            'processed_at' => now(),
        ]);
    }
}
