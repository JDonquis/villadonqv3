<?php

namespace App\Services;

use App\Models\BalancePayment;
use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Models\SchoolLapse;
use App\Models\Student;
use App\Support\BalanceMonthStatus;
use App\Support\EducationLevel;
use App\Support\PaymentDeadline;
use Carbon\Carbon;
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

    public const MONTH_ES_SHORT = [
        'september' => 'Sep',
        'october' => 'Oct',
        'november' => 'Nov',
        'december' => 'Dic',
        'january' => 'Ene',
        'february' => 'Feb',
        'march' => 'Mar',
        'april' => 'Abr',
        'may' => 'May',
        'june' => 'Jun',
        'july' => 'Jul',
        'august' => 'Ago',
    ];

    public const MONTH_ORDER = [
        'september', 'october', 'november', 'december',
        'january', 'february', 'march', 'april',
        'may', 'june', 'july', 'august',
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

    /** @var array<int, BalanceStudent> balance_student_id => BalanceStudent */
    private array $balances = [];

    /** @var MainConfig */
    private MainConfig $config;

    public function __construct(?MainConfig $config = null)
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

        // Store balances for vencido checks later
        foreach ($balances as $id => $balance) {
            $nature->balances[$id] = $balance;
        }

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
                $before = $current - (float) $step->amount;
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
     * Determina si el mes estaba VENCIDO en la fecha del pago.
     * Usa la misma lógica que BalanceMonthStatus::isDue() pero con valores
     * históricos en la fecha del pago (no "ahora").
     */
    private function wasVencidoAt(BalancePayment $application): bool
    {
        // Inscripción no tiene concepto de vencido/futuro
        if ($application->is_inscription) {
            return false;
        }

        $balance = $this->balances[$application->balance_student_id] ?? null;
        $payment = $application->payment;

        if (! $balance || ! $payment) {
            return false;
        }

        // Fecha del pago (raw_date = Y-m-d)
        $paymentDate = Carbon::parse($payment->raw_date ?? $payment->date);

        // Configuración del colegio (usamos la actual; rara vez cambia en medio del año)
        $dayOfMonthlyPayment = $this->config->day_of_monthly_payment ?? 1;
        $gracePeriod = $this->config->grace_period ?? 0;

        // Lapso escolar de este balance
        $balanceLapse = $balance->schoolLapse;

        if (! $balanceLapse) {
            return false;
        }

        // Lapso "activo" en la fecha del pago: el que contiene esa fecha
        // Si no hay ninguno, usamos el lapso del propio balance
        $activeLapseAtPayment = SchoolLapse::where('start', '<=', $paymentDate)
            ->where('end', '>=', $paymentDate)
            ->first();

        if (! $activeLapseAtPayment) {
            $activeLapseAtPayment = $balanceLapse;
        }

        // Posición del lapso del balance respecto al lapso activo en la fecha del pago
        $lapsePosition = BalanceMonthStatus::lapsePosition($balance, $activeLapseAtPayment);

        // Índice del mes en el orden escolar (sept=0, oct=1, ...)
        $monthIndex = array_search($application->month, self::MONTH_ORDER, true);
        if ($monthIndex === false) {
            $monthIndex = 0;
        }

        // Índice del mes "corriente" EN LA FECHA DEL PAGO (mes del pago)
        $paymentMonth = strtolower($paymentDate->format('F'));
        $currentMonthIndex = array_search($paymentMonth, self::MONTH_ORDER, true);
        if ($currentMonthIndex === false) {
            $currentMonthIndex = 0;
        }

        // ¿El mes corriente estaba vencido en la fecha del pago?
        $currentMonthPastDue = PaymentDeadline::currentMonthPastDue($dayOfMonthlyPayment, $gracePeriod);

        // Usamos la misma lógica de isDue() pero con valores históricos
        return BalanceMonthStatus::isDue(
            $monthIndex,
            $currentMonthIndex,
            $currentMonthPastDue,
            $lapsePosition
        );
    }

    /**
     * Etiqueta de una porción del pago aplicada al balance.
     */
    public function labelFor(BalancePayment $application): string
    {
        $before = $this->before[$application->id] ?? null;
        $after = $this->after[$application->id] ?? null;

        if ($before === null || $after === null) {
            return $application->is_inscription ? 'Abono Inscripción' : '';
        }

        if ($application->is_inscription) {
            // Inscription: deuda es negativa. before < 0 = debía; after >= 0 = saldó.
            return ($before < 0 && $after >= 0)
                ? 'Inscripción pagada'
                : 'Abono Inscripción';
        }

        $month = self::MONTH_ES_SHORT[$application->month]
            ?? self::MONTH_ES[$application->month]
            ?? ucfirst((string) $application->month);

        // Determinar si el mes estaba VENCIDO en la fecha del pago
        $vencido = $this->wasVencidoAt($application);

        // after = deuda restante DESPUÉS del pago (negativo = todavía debe, 0 = saldado, positivo = crédito)
        // before = deuda restante ANTES del pago

        if ($vencido) {
            // Mes vencido: pagar deuda
            return $after >= 0
                ? 'Pago '.$month
                : 'Abono '.$month;
        }

        // Mes futuro (no vencido): pago adelantado.
        // after >= 0 => el pago cubrió el mes completo.
        return ($after >= 0)
            ? 'Pago Adelantado '.$month
            : 'Abono Adelantado '.$month;
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

        foreach ($payment->allocations ?? [] as $allocation) {
            $name = $allocation->paymentConcept?->name;

            if ($name && ! in_array($name, $parts, true)) {
                $parts[] = $name;
            }
        }

        $conceptName = $payment->paymentConcept?->name;

        if ($conceptName && ! in_array($conceptName, $parts, true)) {
            $parts[] = $conceptName;
        }

        return implode(', ', $parts);
    }

    /**
     * Concepto del pago correspondiente a UN estudiante concreto: sólo las
     * aplicaciones al balance cuyo `balance_student` pertenece a ese estudiante,
     * más el concepto del recibo (compartido por todo el pago).
     */
    public function conceptForStudent($payment, $student): string
    {
        $parts = [];
        $studentId = (int) $student->id;

        foreach ($payment->balancePayments as $application) {
            if ($this->studentIdFor((int) $application->balance_student_id) !== $studentId) {
                continue;
            }

            $label = $this->labelFor($application);

            if ($label !== '' && ! in_array($label, $parts, true)) {
                $parts[] = $label;
            }
        }

        foreach ($payment->allocations ?? [] as $allocation) {
            if ((int) $allocation->student_id !== $studentId) {
                continue;
            }

            $name = $allocation->paymentConcept?->name;

            if ($name && ! in_array($name, $parts, true)) {
                $parts[] = $name;
            }
        }

        $conceptName = $payment->paymentConcept?->name;

        if ($conceptName && ! in_array($conceptName, $parts, true)) {
            $parts[] = $conceptName;
        }

        return implode(', ', $parts);
    }
}