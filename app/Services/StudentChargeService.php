<?php

namespace App\Services;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\MainConfig;
use App\Models\PaymentConcept;
use App\Models\SchoolLapse;
use App\Models\Student;
use App\Models\StudentCharge;

class StudentChargeService
{
    /**
     * Tipos de cargo separados del balance y su nombre visible.
     */
    public const TYPES = [
        StudentCharge::TYPE_AME => 'Seguro de atención primaria (AME)',
        StudentCharge::TYPE_INVESTMENT_PLAN => 'Plan de inversión',
    ];

    /**
     * Precio configurado por tipo de cargo.
     *
     * @return array<string, float>
     */
    public function conceptPrices(): array
    {
        $config = MainConfig::first();

        return [
            StudentCharge::TYPE_AME => (float) ($config->ame_price ?? 0),
            StudentCharge::TYPE_INVESTMENT_PLAN => (float) ($config->investment_plan_price ?? 0),
        ];
    }

    /**
     * Crea/actualiza los conceptos de pago del sistema con el precio configurado.
     */
    public function syncConcepts(): void
    {
        $prices = $this->conceptPrices();

        foreach (self::TYPES as $type => $name) {
            PaymentConcept::updateOrCreate(
                ['type' => $type],
                [
                    'name' => $name,
                    'price' => $prices[$type],
                    'status' => 1,
                ]
            );
        }
    }

    /**
     * Genera los cargos del estudiante para el periodo si el precio es mayor a 0.
     */
    public function generateForStudent(Student $student, ?SchoolLapse $schoolLapse = null): void
    {
        $schoolLapse = $schoolLapse ?? SchoolLapse::where('status', 1)->first();

        if (! $schoolLapse) {
            return;
        }

        $prices = $this->conceptPrices();
        $concepts = PaymentConcept::whereNotNull('type')->get()->keyBy('type');
        $multiplier = $student->is_exempt ? (1 - (($student->exemption_percentage ?? 0) / 100)) : 1;

        foreach (self::TYPES as $type => $name) {
            $amount = round($prices[$type] * $multiplier, 2);

            if ($amount <= 0) {
                continue;
            }

            StudentCharge::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'school_lapse_id' => $schoolLapse->id,
                    'type' => $type,
                ],
                [
                    'payment_concept_id' => $concepts->get($type)?->id,
                    'amount' => $amount,
                    'paid_amount' => 0,
                    'status' => BalanceStudentStatusEnum::Pending->value,
                ]
            );
        }
    }

    /**
     * Genera los cargos del periodo para todos los estudiantes inscritos en él.
     */
    public function generateForLapse(?SchoolLapse $schoolLapse = null): void
    {
        $schoolLapse = $schoolLapse ?? SchoolLapse::where('status', 1)->first();

        if (! $schoolLapse) {
            return;
        }

        if (max($this->conceptPrices()) <= 0) {
            return;
        }

        $students = Student::where('status', '!=', 0)
            ->whereHas('inscriptions', function ($query) use ($schoolLapse) {
                $query->where('school_lapse_id', $schoolLapse->id);
            })
            ->get();

        foreach ($students as $student) {
            $this->generateForStudent($student, $schoolLapse);
        }
    }

    /**
     * Recalcula montos de cargos impagos con el precio actual.
     * Si se pasa un periodo, solo ese periodo; sin periodo, todos los periodos.
     */
    public function recalculatePendingCharges(?SchoolLapse $schoolLapse = null): void
    {
        $prices = $this->conceptPrices();
        $concepts = PaymentConcept::whereNotNull('type')->get()->keyBy('type');

        $query = StudentCharge::where('status', '!=', BalanceStudentStatusEnum::Paid->value)
            ->with('student');

        if ($schoolLapse) {
            $query->where('school_lapse_id', $schoolLapse->id);
        }

        $charges = $query->get();

        foreach ($charges as $charge) {
            if (! $charge->student || ! isset($prices[$charge->type])) {
                continue;
            }

            $multiplier = $charge->student->is_exempt
                ? (1 - (($charge->student->exemption_percentage ?? 0) / 100))
                : 1;

            $charge->amount = max(0, round($prices[$charge->type] * $multiplier, 2));
            $charge->payment_concept_id = $concepts->get($charge->type)?->id ?? $charge->payment_concept_id;

            $paid = (float) $charge->paid_amount;

            $charge->status = match (true) {
                $paid > 0 && $paid >= (float) $charge->amount => BalanceStudentStatusEnum::Paid->value,
                $paid > 0 => BalanceStudentStatusEnum::PartiallyPaid->value,
                default => BalanceStudentStatusEnum::Pending->value,
            };

            $charge->save();
        }
    }
}
