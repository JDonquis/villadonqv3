<?php

namespace App\Services;

use App\Models\BalancePayment;
use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Models\Student;
use App\Support\EducationLevel;
use Illuminate\Support\Collection;

/**
 * Clasifica un pago (o cada porción de un pago aplicada al balance) con la
 * terminología del libro de ventas y del tooltip de Estados de Cuenta.
 *
 * La deuda se guarda con signo negativo (se debe) y positivo (a favor). Cada
 * fila de `balance_payments` sólo existe si en ese momento la deuda era > 0,
 * así que reconstruimos el estado antes/después de cada aplicación
 * reproduciendo el historial hacia atrás desde el saldo actual.
 */
class PaymentNature
{
    public const MONTH_ES = [
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

    /** @var array<int, float> Deuda restante antes de la aplicación. */
    private array $before = [];

    /** @var array<int, float> Deuda restante después de la aplicación. */
    private array $after = [];

    /** @var array<int, float> Precio mensual real por estudiante. */
    private array $monthlyPrice = [];

    /** @var array<int, float> Precio de inscripción real por estudiante. */
    private array $inscriptionPrice = [];

    /** @var array<int, int> balance_student_id => student_id */
    private array $studentsById = [];

    public function __construct(private ?MainConfig $config = null)
    {
        $this->config = $config ?: MainConfig::first();
    }

    /**
     * Construye el índice histórico para los pagos indicados.
     */
    public static function indexFor(Collection $payments, ?MainConfig $config = null): self
    {
        return self::indexForBalancePayments(
            $payments->flatMap(fn ($payment) => $payment->balancePayments)->values(),
            $config
        );
    }

    /**
     * Construye el índice histórico a partir de las aplicaciones al balance.
     * Se cargan TODAS las aplicaciones de esos balances (no sólo las visibles)
     * para que el estado antes/después de cada pago sea correcto.
     */
    public static function indexForBalancePayments(Collection $balancePayments, ?MainConfig $config = null): self
    {
        $nature = new self($config);

        $balanceIds = $balancePayments
            ->pluck('balance_student_id')
            ->unique()
            ->filter()
            ->values();

        if ($balanceIds->isEmpty()) {
            return $nature;
        }

        $balances = BalanceStudent::whereIn('id', $balanceIds)->get()->keyBy('id');

        $applications = BalancePayment::whereIn('balance_student_id', $balanceIds)
            ->with('payment:id,date')
            ->get()
            ->all();

        usort($applications, function (BalancePayment $a, BalancePayment $b) {
            $dateA = (string) optional($a->payment)->raw_date;
            $dateB = (string) optional($b->payment)->raw_date;

            return [$dateA, $a->id] <=> [$dateB, $b->id];
        });

        $groups = [];

        foreach ($applications as $application) {
            $balance = $balances->get($application->balance_student_id);

            if (! $balance) {
                continue;
            }

            $nature->studentsById[(int) $balance->id] = (int) $balance->student_id;

            $key = $application->is_inscription ? 'inscription' : (string) $application->month;
            $groups[$balance->id.'|'.$key][] = $application;
        }

        foreach ($groups as $grouped) {
            /** @var BalancePayment $last */
            $last = $grouped[count($grouped) - 1];
            $balance = $balances->get($last->balance_student_id);

            $current = (float) ($last->is_inscription ? $balance->inscription : $balance->{$last->month});

            foreach (array_reverse($grouped) as $step) {
                $before = $current + (float) $step->amount;
                $nature->before[$step->id] = $before;
                $nature->after[$step->id] = $current;
                $current = $before;
            }
        }

        return $nature;
    }

    public function studentIdFor(int $balanceStudentId): ?int
    {
        return $this->studentsById[$balanceStudentId] ?? null;
    }

    public function monthlyPriceFor(?int $studentId): float
    {
        if (! $studentId) {
            return 0.0;
        }

        return $this->monthlyPrice[$studentId] ??= $this->priceForStudent($studentId, false);
    }

    public function inscriptionPriceFor(?int $studentId, ?int $courseId): float
    {
        if (! $studentId) {
            return 0.0;
        }

        return $this->inscriptionPrice[$studentId] ??= $this->priceForStudent($studentId, true, $courseId);
    }

    private function priceForStudent(int $studentId, bool $inscription, ?int $courseId = null): float
    {
        $student = Student::find($studentId);

        if (! $student) {
            return 0.0;
        }

        if ($inscription) {
            $price = EducationLevel::inscriptionPrice($this->config, $courseId ?: $student->course_id);
        } else {
            $price = (float) ($this->config->monthly_payment ?? 0);
        }

        if ($student->is_exempt) {
            $price *= 1 - ((float) ($student->exemption_percentage ?? 0) / 100);
        }

        return $price;
    }

    /**
     * Etiqueta de una porción del pago aplicada al balance.
     */
    public function labelFor(BalancePayment $application): string
    {
        $before = $this->before[$application->id] ?? null;
        $after = $this->after[$application->id] ?? null;

        if ($before === null || $after === null) {
            return $application->is_inscription ? 'Abono a Inscripción' : '';
        }

        if ($application->is_inscription) {
            return ($before > 0 && $after <= 0)
                ? 'Pago de Inscripción'
                : 'Abono a Inscripción';
        }

        $month = self::MONTH_ES[$application->month] ?? ucfirst((string) $application->month);
        $price = $this->monthlyPriceFor($this->studentIdFor((int) $application->balance_student_id));

        if ($before > 0) {
            return $after <= 0
                ? 'Pago de Mensualidad '.$month
                : 'Abono a Mensualidad '.$month;
        }

        return ($price > 0 && $after < $price)
            ? 'Abono Anticipado - '.$month
            : 'Pago Adelantado - Mensualidad '.$month;
    }

    /**
     * Concepto completo de un pago: etiquetas del balance + concepto del recibo.
     */
    public function conceptFor($payment): string
    {
        $parts = [];

        foreach ($payment->balancePayments as $application) {
            $label = $this->labelFor($application);

            if ($label !== '' && ! in_array($label, $parts, true)) {
                $parts[] = $label;
            }
        }

        $conceptName = $payment->paymentConcept?->name;

        if ($conceptName && ! in_array($conceptName, $parts, true)) {
            $parts[] = $conceptName;
        }

        return implode(', ', $parts);
    }
}