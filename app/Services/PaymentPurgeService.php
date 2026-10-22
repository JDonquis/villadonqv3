<?php

namespace App\Services;

use App\Models\BalancePayment;
use App\Models\Payment;
use App\Models\SchoolLapse;
use App\Models\StudentCharge;
use App\Models\StudentChargePayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PaymentPurgeService
{
    public function resolveLapse(?int $lapseId): SchoolLapse
    {
        if ($lapseId) {
            return SchoolLapse::findOrFail($lapseId);
        }

        $lapse = SchoolLapse::where('status', 1)->orderByDesc('id')->first();

        if (! $lapse) {
            throw new \RuntimeException('No hay un período escolar activo. Indique --lapse.');
        }

        return $lapse;
    }

    /**
     * IDs de pagos asociados al período.
     *
     * Primero toma los pagos con aplicaciones en balances o cargos del período.
     * Si no se limita a esos, agrega los pagos sin vínculo contable cuya fecha
     * cae dentro del período (conceptos libres, por ejemplo).
     *
     * @return array<int,int>
     */
    public function collectPaymentIds(SchoolLapse $lapse, bool $onlyLinked = false): array
    {
        $linked = Payment::query()
            ->where(function ($query) use ($lapse) {
                $query->whereHas('balancePayments.balanceStudent', function ($sub) use ($lapse) {
                    $sub->where('school_lapse_id', $lapse->id);
                })->orWhereHas('studentChargePayments.studentCharge', function ($sub) use ($lapse) {
                    $sub->where('school_lapse_id', $lapse->id);
                });
            })
            ->pluck('id');

        if ($onlyLinked) {
            return $linked->map(fn ($id) => (int) $id)->all();
        }

        $unlinked = Payment::query()
            ->whereNotIn('id', $linked)
            ->whereDoesntHave('balancePayments')
            ->whereDoesntHave('studentChargePayments')
            ->whereBetween('date', [$lapse->start, $lapse->end])
            ->pluck('id');

        return $linked
            ->merge($unlinked)
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    public function preview(SchoolLapse $lapse, bool $onlyLinked = false): array
    {
        $ids = $this->collectPaymentIds($lapse, $onlyLinked);

        $payments = $this->payments($ids);
        $active = $payments->filter(fn (Payment $payment) => (int) $payment->status === 1);

        return [
            'lapse_id' => $lapse->id,
            'lapse_start' => $lapse->start,
            'lapse_end' => $lapse->end,
            'only_linked' => $onlyLinked,
            'payment_ids' => $ids,
            'payments_count' => $payments->count(),
            'active_count' => $active->count(),
            'already_deleted_count' => $payments->count() - $active->count(),
            'students_count' => $this->studentsCount($ids),
            'balances_count' => BalancePayment::whereIn('payment_id', $ids)
                ->distinct()
                ->count('balance_student_id'),
            'charges_count' => StudentChargePayment::whereIn('payment_id', $ids)
                ->distinct()
                ->count('student_charge_id'),
            'total_in_dolars' => round($active->sum(fn (Payment $payment) => (float) $payment->total_in_dolars), 2),
            'total_in_bs' => round($active->sum(fn (Payment $payment) => (float) $payment->total_in_bs), 2),
            'sample' => $this->sample($payments),
        ];
    }

    /**
     * Elimina físicamente los pagos del período y revierte sus efectos en
     * balances y cargos antes de borrar las filas.
     */
    public function purge(SchoolLapse $lapse, bool $onlyLinked = false): array
    {
        $ids = $this->collectPaymentIds($lapse, $onlyLinked);

        if ($ids === []) {
            return [
                'payments_deleted' => 0,
                'active_reverted' => 0,
                'already_deleted' => 0,
                'balances_reverted' => 0,
                'charges_reverted' => 0,
                'total_in_dolars' => 0.0,
            ];
        }

        return DB::transaction(function () use ($ids) {
            $balanceService = app(BalanceService::class);
            $payments = $this->payments($ids);

            $balances = [];
            $charges = [];
            $activeReverted = 0;
            $alreadyDeleted = 0;
            $total = 0.0;

            foreach ($payments as $payment) {
                foreach ($payment->students as $student) {
                    BalancePayment::where('payment_id', $payment->id)
                        ->whereHas('balanceStudent', function ($query) use ($student) {
                            $query->where('student_id', $student->id);
                        })
                        ->pluck('balance_student_id')
                        ->each(function ($balanceId) use (&$balances) {
                            $balances[(int) $balanceId] = true;
                        });

                    $balanceService->revertStudentBalance($payment, $student);
                }

                $chargePayments = StudentChargePayment::where('payment_id', $payment->id)->get();
                $chargeIds = $chargePayments->pluck('student_charge_id')->unique();

                foreach ($chargePayments as $chargePayment) {
                    $chargePayment->delete();
                }

                foreach (StudentCharge::whereIn('id', $chargeIds)->get() as $charge) {
                    $charge->syncFromPayments();
                    $charges[(int) $charge->id] = true;
                }

                $payment->allocations()->delete();

                if ((int) $payment->status === 1) {
                    $total += (float) $payment->total_in_dolars;
                    $activeReverted++;
                } else {
                    $alreadyDeleted++;
                }

                Payment::withoutEvents(fn () => $payment->forceDelete());
            }

            return [
                'payments_deleted' => $payments->count(),
                'active_reverted' => $activeReverted,
                'already_deleted' => $alreadyDeleted,
                'balances_reverted' => count($balances),
                'charges_reverted' => count($charges),
                'total_in_dolars' => round($total, 2),
            ];
        });
    }

    /**
     * @param  array<int,int>  $ids
     * @return Collection<int,Payment>
     */
    private function payments(array $ids): Collection
    {
        if ($ids === []) {
            return collect();
        }

        return Payment::whereIn('id', $ids)
            ->with([
                'students:id,name,last_name',
                'paymentConcept:id,name',
                'accountPayment.method:id,name',
            ])
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<int,int>  $ids
     */
    private function studentsCount(array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return (int) DB::table('payment_students')
            ->whereIn('payment_id', $ids)
            ->distinct()
            ->count('student_id');
    }

    /**
     * @param  Collection<int,Payment>  $payments
     * @return array<int,array<string,mixed>>
     */
    private function sample(Collection $payments): array
    {
        return $payments
            ->take(15)
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'date' => $payment->raw_date,
                'students' => $payment->students
                    ->map(fn ($student) => trim($student->name.' '.$student->last_name))
                    ->implode(', '),
                'concept' => $payment->paymentConcept?->name ?? 'Mensualidad / Inscripción',
                'method' => $payment->accountPayment?->method?->name ?? '—',
                'total_in_dolars' => (float) $payment->total_in_dolars,
                'status' => (int) $payment->status,
            ])
            ->values()
            ->all();
    }
}
