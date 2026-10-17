<?php

namespace App\Services;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\BalanceAnnulment;
use App\Models\BalanceStudent;
use App\Models\SchoolLapse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BalanceMaintenanceService
{
    public const MONTHS = [
        'september',
        'october',
        'november',
        'december',
        'january',
        'february',
        'march',
        'april',
        'may',
        'june',
        'july',
        'august',
    ];

    public const MONTHS_ES = [
        'september' => 'Septiembre',
        'october' => 'Octubre',
        'november' => 'Noviembre',
        'december' => 'Diciembre',
        'january' => 'Enero',
        'february' => 'Febrero',
        'march' => 'Marzo',
        'april' => 'Abril',
        'may' => 'Mayo',
        'june' => 'Junio',
        'july' => 'Julio',
        'august' => 'Agosto',
    ];

    /**
     * Calcula (sin persistir) qué se anularía.
     */
    public function preview(?int $lapseId, string $upToMonth, bool $includeInscription, array $filters = []): array
    {
        [$upToIndex, $balances] = $this->collect($lapseId, $upToMonth, $filters);

        $affected = 0;
        $skipped = 0;
        $total = 0.0;
        $perBalance = [];

        foreach ($balances as $balance) {
            $monthsToAnnul = [];
            $paidMonths = $balance->balancePayments
                ->groupBy('month')
                ->map(fn ($items) => (float) $items->sum('amount'));

            for ($i = 0; $i <= $upToIndex; $i++) {
                $month = self::MONTHS[$i];

                if (($paidMonths[$month] ?? 0) > 0.005) {
                    $skipped++;

                    continue;
                }

                $value = (float) $balance->$month;

                if ($balance->isMonthAnnulled($month) && $value == 0.0) {
                    continue; // ya anulado
                }

                $monthsToAnnul[$month] = $value;
            }

            $inscriptionPaid = (float) $balance->balancePayments->where('is_inscription', true)->sum('amount');
            $annulInscription = $includeInscription
                && $inscriptionPaid <= 0.005
                && (float) $balance->inscription < 0;

            if ($monthsToAnnul === [] && ! $annulInscription) {
                continue;
            }

            $affected++;

            foreach ($monthsToAnnul as $value) {
                $total += abs(min(0, $value));
            }

            if ($annulInscription) {
                $total += abs((float) $balance->inscription);
            }

            $perBalance[] = [
                'balance_id' => $balance->id,
                'student' => trim(($balance->student?->name ?? '').' '.($balance->student?->last_name ?? '')),
                'months' => array_keys($monthsToAnnul),
                'inscription' => $annulInscription,
            ];
        }

        return [
            'up_to_month' => $upToMonth,
            'include_inscription' => $includeInscription,
            'affected_count' => $affected,
            'skipped_count' => $skipped,
            'total_annulled' => round($total, 2),
            'balances' => $perBalance,
        ];
    }

    /**
     * Anula los meses <= corte para los balances del lapso, guardando snapshot.
     */
    public function annul(?int $lapseId, string $upToMonth, bool $includeInscription, array $filters = []): array
    {
        DB::beginTransaction();

        try {
            [$upToIndex, $balances] = $this->collect($lapseId, $upToMonth, $filters);

            $snapshot = [];
            $total = 0.0;
            $affected = 0;
            $skipped = 0;

            $nextMonth = $upToIndex < (count(self::MONTHS) - 1) ? self::MONTHS[$upToIndex + 1] : null;

            foreach ($balances as $balance) {
                $paidMonths = $balance->balancePayments
                    ->groupBy('month')
                    ->map(fn ($items) => (float) $items->sum('amount'));

                $entry = [
                    'balance_id' => $balance->id,
                    'start_month' => $balance->start_month,
                    'general_status' => $this->statusValue($balance->status),
                    'months' => [],
                    'inscription' => null,
                ];

                $changed = false;

                for ($i = 0; $i <= $upToIndex; $i++) {
                    $month = self::MONTHS[$i];

                    if (($paidMonths[$month] ?? 0) > 0.005) {
                        $skipped++;

                        continue;
                    }

                    $value = (float) $balance->$month;
                    $status = $this->statusValue($balance->{$month.'_status'});

                    if ($status === BalanceStudentStatusEnum::Annulled->value && $value == 0.0) {
                        continue; // ya anulado
                    }

                    $entry['months'][$month] = ['value' => $value, 'status' => $status];
                    $total += abs(min(0, $value));
                    $balance->$month = 0;
                    $balance->{$month.'_status'} = BalanceStudentStatusEnum::Annulled->value;
                    $changed = true;
                }

                if ($includeInscription) {
                    $inscriptionPaid = (float) $balance->balancePayments->where('is_inscription', true)->sum('amount');

                    if ($inscriptionPaid <= 0.005 && (float) $balance->inscription < 0) {
                        $entry['inscription'] = [
                            'value' => (float) $balance->inscription,
                            'status' => $this->statusValue($balance->inscription_status),
                        ];
                        $total += abs((float) $balance->inscription);
                        $balance->inscription = 0;
                        $balance->inscription_status = BalanceStudentStatusEnum::Annulled->value;
                        $changed = true;
                    }
                }

                if (! $changed) {
                    continue;
                }

                $balance->start_month = $nextMonth;
                $this->recomputeGeneralStatus($balance);
                $balance->save();

                $entry['new_start_month'] = $nextMonth;
                $snapshot[] = $entry;
                $affected++;
            }

            $annulment = BalanceAnnulment::create([
                'school_lapse_id' => $lapseId,
                'up_to_month' => $upToMonth,
                'include_inscription' => $includeInscription,
                'filters' => $filters,
                'snapshot' => $snapshot,
                'affected_count' => $affected,
                'total_annulled' => round($total, 2),
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return [
                'annulment_id' => $annulment->id,
                'affected_count' => $affected,
                'skipped_count' => $skipped,
                'total_annulled' => round($total, 2),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Restaura el estado previo de una anulación.
     */
    public function undo(int $annulmentId): array
    {
        $annulment = BalanceAnnulment::findOrFail($annulmentId);

        if ($annulment->isUndone()) {
            return ['restored' => 0, 'message' => 'Esta anulación ya fue deshecha.'];
        }

        DB::beginTransaction();

        try {
            $restored = 0;

            foreach ($annulment->snapshot ?? [] as $entry) {
                $balance = BalanceStudent::find($entry['balance_id'] ?? null);

                if (! $balance) {
                    continue;
                }

                foreach (($entry['months'] ?? []) as $month => $data) {
                    $balance->$month = $data['value'];
                    $balance->{$month.'_status'} = $data['status'];
                }

                if (! empty($entry['inscription'])) {
                    $balance->inscription = $entry['inscription']['value'];
                    $balance->inscription_status = $entry['inscription']['status'];
                }

                $balance->start_month = $entry['start_month'] ?? null;
                $balance->status = $entry['general_status'] ?? $balance->status;
                $balance->save();

                $restored++;
            }

            $annulment->undone_at = now();
            $annulment->save();

            DB::commit();

            return ['restored' => $restored, 'message' => "Se restauraron {$restored} balances."];
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }
    }

    public function history(?int $lapseId = null): array
    {
        return BalanceAnnulment::query()
            ->when($lapseId, fn ($q) => $q->where('school_lapse_id', $lapseId))
            ->with('createdBy:id,name,last_name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (BalanceAnnulment $a) => [
                'id' => $a->id,
                'school_lapse_id' => $a->school_lapse_id,
                'up_to_month' => $a->up_to_month,
                'up_to_month_label' => self::MONTHS_ES[$a->up_to_month] ?? $a->up_to_month,
                'include_inscription' => $a->include_inscription,
                'affected_count' => $a->affected_count,
                'total_annulled' => (float) $a->total_annulled,
                'created_by' => $a->createdBy
                    ? trim($a->createdBy->name.' '.$a->createdBy->last_name)
                    : '—',
                'created_at' => $a->created_at?->translatedFormat('d/m/Y H:i'),
                'undone' => $a->isUndone(),
            ])
            ->all();
    }

    /**
     * @return array{0:int,1:\Illuminate\Support\Collection<int,BalanceStudent>}
     */
    private function collect(?int $lapseId, string $upToMonth, array $filters): array
    {
        $upToIndex = array_search($upToMonth, self::MONTHS, true);

        if ($upToIndex === false) {
            $upToIndex = -1;
        }

        $lapseId ??= SchoolLapse::where('status', 1)->value('id');

        $query = BalanceStudent::query()
            ->where('school_lapse_id', $lapseId)
            ->with(['student', 'balancePayments']);

        if (! empty($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (! empty($filters['course_id'])) {
            $query->whereHas('student', fn ($q) => $q->where('course_id', $filters['course_id']));
        }

        if (! empty($filters['section_id'])) {
            $query->whereHas('student', fn ($q) => $q->where('section_id', $filters['section_id']));
        }

        return [$upToIndex, $query->get()];
    }

    private function statusValue($status): ?string
    {
        return $status instanceof BalanceStudentStatusEnum ? $status->value : $status;
    }

    private function recomputeGeneralStatus(BalanceStudent $balance): void
    {
        $statuses = [];

        if ($balance->inscription_status) {
            $statuses[] = $this->statusValue($balance->inscription_status);
        }

        foreach (self::MONTHS as $month) {
            $statuses[] = $this->statusValue($balance->{$month.'_status'});
        }

        $allPaid = collect($statuses)->every(
            fn ($status) => in_array($status, [BalanceStudentStatusEnum::Paid->value, BalanceStudentStatusEnum::Annulled->value], true)
        );

        if ($allPaid) {
            $balance->status = BalanceStudentStatusEnum::Paid->value;

            return;
        }

        if (collect($statuses)->contains(BalanceStudentStatusEnum::Debt->value)) {
            $balance->status = BalanceStudentStatusEnum::Debt->value;

            return;
        }

        $balance->status = collect($statuses)->contains(BalanceStudentStatusEnum::PartiallyPaid->value)
            ? BalanceStudentStatusEnum::PartiallyPaid->value
            : BalanceStudentStatusEnum::Pending->value;
    }
}
