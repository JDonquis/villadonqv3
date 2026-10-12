<?php

namespace App\Services;

use App\Models\MainConfig;
use App\Models\Payment;
use Carbon\Carbon;

class PaymentInvoiceService
{
    /**
     * Construye los datos para la factura de un pago.
     *
     * @param  int|null  $repStudentId  Estudiante cuyo representante se mostrará.
     * @param  string  $repType  'legal' (primario) | 'second' (no legal / secundario).
     */
    public function buildData(Payment $payment, ?int $repStudentId = null, string $repType = 'legal'): array
    {
        $config = MainConfig::first();
        $rate = (float) $payment->exchange_rate;
        $nature = PaymentNature::indexFor(collect([$payment]));

        $students = [];

        foreach ($payment->students as $student) {
            $lines = $this->linesForStudent($payment, $student, $nature, $rate);
            $subtotal = array_sum(array_column($lines, 'amount_bs'));

            $students[] = [
                'name' => trim($student->name.' '.$student->last_name),
                'course' => $student->course?->name ?? '',
                'section' => $student->section?->name ?? '',
                'lines' => $lines,
                'subtotal_bs' => round($subtotal, 2),
            ];
        }

        return [
            'institution' => [
                'name' => $config?->name ?? 'INSTITUCIÓN EDUCATIVA',
                'rif' => $config?->rif ? strtoupper($config->rif) : null,
                'address' => $config?->address,
                'phone' => $config?->phone_number,
                'email' => $config?->email,
                'motto' => $config?->motto,
                'logo' => $this->logoDataUri($config?->entity_shield),
            ],
            'payment' => [
                'id' => $payment->id,
                'reference' => $payment->reference,
                'transaction_date' => $payment->raw_date
                    ? Carbon::parse($payment->raw_date)->translatedFormat('d/m/Y')
                    : Carbon::parse($payment->date)->translatedFormat('d/m/Y'),
                'report_date' => $payment->reported_date
                    ? $payment->reported_date->translatedFormat('d/m/Y')
                    : null,
            ],
            'representative' => $this->resolveRepresentative($payment, $repStudentId, $repType),
            'students' => $students,
            'total_bs' => (float) $payment->total_in_bs,
            'generated_at' => Carbon::now()->translatedFormat('d/m/Y H:i'),
        ];
    }

    /**
     * Líneas de concepto de UN estudiante dentro del pago.
     * Toma las aplicaciones al balance (meses/inscripción) y las asignaciones
     * a conceptos especiales (AME, Plan, etc.). Montos en Bs.
     */
    private function linesForStudent(Payment $payment, $student, PaymentNature $nature, float $rate): array
    {
        $studentId = (int) $student->id;
        $lines = [];

        foreach ($payment->balancePayments as $application) {
            if ($nature->studentIdFor((int) $application->balance_student_id) !== $studentId) {
                continue;
            }

            $label = $nature->labelFor($application) ?: 'Mensualidad';
            $usd = (float) $application->amount;

            $lines[] = [
                'label' => $label,
                'amount_usd' => $usd,
                'amount_bs' => round($usd * $rate, 2),
            ];
        }

        foreach ($payment->allocations ?? [] as $allocation) {
            if ((int) $allocation->student_id !== $studentId) {
                continue;
            }

            $usd = (float) $allocation->amount;

            $lines[] = [
                'label' => $allocation->paymentConcept?->name ?? 'Concepto',
                'amount_usd' => $usd,
                'amount_bs' => round($usd * $rate, 2),
            ];
        }

        if ($lines === []) {
            $pivotUsd = (float) ($student->pivot->amount_in_dolars ?? 0);
            $lines[] = [
                'label' => $payment->paymentConcept?->name ?? 'Mensualidad / Inscripciones',
                'amount_usd' => $pivotUsd,
                'amount_bs' => round($pivotUsd * $rate, 2),
            ];
        }

        return $lines;
    }

    /**
     * Datos del representante (legal primario o segundo/no legal).
     */
    private function resolveRepresentative(Payment $payment, ?int $repStudentId, string $repType): array
    {
        $student = $repStudentId
            ? $payment->students->firstWhere('id', $repStudentId)
            : null;

        $student ??= $payment->students->first();
        $representative = $student?->representative;

        if ($repType === 'second' && $representative) {
            return [
                'name' => trim(($representative->second_representative_name ?? '').' '.($representative->second_representative_last_name ?? '')),
                'document' => trim(($representative->second_document_type ?? '').'-'.($representative->second_representative_ci ?? ''), '-'),
                'phone' => $representative->second_representative_phone_number
                    ?? $representative->second_representative_phone_number2
                    ?? '',
                'email' => $representative->second_representative_email ?? '',
                'relationship' => $representative->second_representative_relationship ?? '',
                'address' => '',
            ];
        }

        $user = $representative?->user;

        return [
            'name' => trim(($user?->name ?? '').' '.($user?->last_name ?? '')),
            'document' => trim(($representative?->document_type ?? '').'-'.($user?->ci ?? ''), '-'),
            'phone' => $user?->phone_number ?? '',
            'email' => $user?->email ?? '',
            'relationship' => $representative?->relationship ?? '',
            'address' => $user?->address ?? '',
        ];
    }

    private function logoDataUri(?string $shield): ?string
    {
        if (! $shield) {
            return null;
        }

        $path = public_path('storage/'.$shield);

        if (! is_file($path)) {
            return null;
        }

        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(file_get_contents($path));
    }
}
