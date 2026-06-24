<?php

namespace App\Observers;

use App\Models\Payment;
use App\Models\PaymentHistory;
use Illuminate\Support\Facades\Auth;

class PaymentObserver
{
    public function created(Payment $payment): void
    {
        $this->logHistory($payment, 'created', null, $payment->toArray());
    }

    public function updated(Payment $payment): void
    {
        $oldData = $payment->getOriginal();
        $newData = $payment->getChanges();

        if (! empty($newData)) {
            $this->logHistory($payment, 'updated', $oldData, $newData);
        }
    }

    public function deleted(Payment $payment): void
    {
        $oldData = $payment->getOriginal();
        $this->logHistory($payment, 'deleted', $oldData, null);
    }

    private function logHistory(Payment $payment, string $action, ?array $oldData, ?array $newData): void
    {
        $userId = Auth::id() ?? 1;

        $payment->load('students');

        $allOldData = null;
        $allNewData = null;

        if ($oldData) {
            $allOldData = array_merge($oldData, [
                'students' => $payment->students->toArray(),
            ]);
        }

        if ($newData) {
            $allNewData = array_merge($newData, [
                'students' => $payment->students->toArray(),
            ]);
        }

        PaymentHistory::create([
            'payment_id' => $payment->id,
            'user_id' => $userId,
            'action' => $action,
            'old_data' => $allOldData,
            'new_data' => $allNewData,
            'created_at' => now(),
        ]);
    }
}
