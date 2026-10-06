<?php

namespace App\Services;

use App\Enums\BalanceStudentStatusEnum;
use App\Enums\UserTypeEnum;
use App\Models\BalancePayment;
use App\Models\BalanceStudent;
use App\Models\EvaluationPlan;
use App\Models\MainConfig;
use App\Models\Matter;
use App\Models\Payment;
use App\Models\Schedule;
use App\Models\SchoolLapse;
use App\Models\Student;
use App\Models\User;
use App\Support\DashboardWidgets;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    private const MONTH_ORDER = [
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

    /**
     * Indicadores del panel administrativo. Todos los cálculos se acotan al
     * período escolar indicado (o al activo) y usan consultas agregadas para
     * evitar el N+1. Sólo se calculan los widgets habilitados para el usuario.
     *
     * @param  array<int, string>|null  $widgets  Claves de DashboardWidgets habilitadas.
     */
    public function getKpiData(?int $schoolLapseId = null, ?array $widgets = null): array
    {
        $lapse = $this->resolveLapse($schoolLapseId);
        $widgets = $widgets ?? array_keys(DashboardWidgets::WIDGETS);
        $has = fn (string $key): bool => in_array($key, $widgets, true);

        $data = ['school_lapse_id' => $lapse?->id];

        if ($has('kpi_enrollment') || $has('kpi_representatives')) {
            $data['total_students'] = Student::where('status', '!=', 0)->count();
            $data['total_representatives'] = Student::where('status', '!=', 0)
                ->whereNotNull('representative_id')
                ->distinct('representative_id')
                ->count('representative_id');
        }

        if ($has('kpi_enrollment')) {
            $data['enrollment'] = $this->enrollmentVariation($lapse);
        }

        if ($has('kpi_representatives')) {
            $data['representatives'] = $this->representativeVariation($lapse);
        }

        if ($has('kpi_staff_total')) {
            $data['total_staff'] = User::where('type_user_id', UserTypeEnum::Administrator->value)->count();
        }

        if ($has('kpi_teachers_total')) {
            $data['total_teachers'] = User::where('type_user_id', UserTypeEnum::Teacher->value)->count();
        }

        if ($has('kpi_matters_total')) {
            $data['total_matters'] = Matter::count();
        }

        if ($has('kpi_schedules')) {
            $data['schedules'] = $this->schedulesSummary($lapse);
        }

        if ($has('kpi_active_period')) {
            $data['active_period'] = $this->activePeriod();
        }

        if ($has('kpi_plans_approved') || $has('kpi_plans_pending') || $has('kpi_plans_rejected')) {
            $data['plans'] = $this->plansSummary($lapse);
        }

        // ---- Información financiera ----
        $moneyKeys = ['kpi_month_income', 'kpi_total_debt', 'kpi_reps_on_track', 'kpi_payments_count'];
        $hasMoney = collect($moneyKeys)->contains(fn ($key) => $has($key));

        if ($hasMoney) {
            $config = MainConfig::first();
            $monthlyPrice = (float) ($config->monthly_payment ?? 0);

            if ($has('kpi_payments_count')) {
                $data['payments_count'] = $this->paymentsCount($lapse);
            }

            if ($has('kpi_total_debt')) {
                $debt = $this->outstandingDebt($lapse);
                $collected = $this->collectedForLapse($lapse);
                $billed = $debt + $collected;

                $data['total_outstanding_debt'] = round($debt, 2);
                $data['total_billed'] = round($billed, 2);
                $data['debt_percentage'] = $billed > 0 ? round(($debt / $billed) * 100, 1) : 0.0;
            }

            if ($has('kpi_reps_on_track')) {
                $reps = $this->representativesUpToDate($lapse);
                $data['collection_rate'] = $reps['total'] > 0 ? round(($reps['up_to_date'] / $reps['total']) * 100, 1) : 0.0;
                $data['representatives_up_to_date'] = $reps['up_to_date'];
                $data['representatives_total'] = $reps['total'];
            }

            if ($has('kpi_month_income')) {
                $target = $this->monthTarget($lapse, $monthlyPrice);
                $income = $this->monthIncome($lapse);

                $data['this_month_income'] = round($income, 2);
                $data['month_target'] = round($target, 2);
                $data['income_percentage'] = $target > 0 ? round(($income / $target) * 100, 1) : 0.0;
            }
        }

        return $data;
    }

    public function resolveLapse(?int $schoolLapseId = null): ?SchoolLapse
    {
        if ($schoolLapseId) {
            $lapse = SchoolLapse::find($schoolLapseId);
            if ($lapse) {
                return $lapse;
            }
        }

        return SchoolLapse::where('status', 1)->first()
            ?? SchoolLapse::orderByDesc('start')->first();
    }

    /**
     * Secciones (curso + sección) con horario cargado vs. total, ya que el
     * horario se crea para una sección de un año/curso específico.
     */
    private function schedulesSummary(?SchoolLapse $lapse): array
    {
        $withSchedule = $lapse
            ? Schedule::where('school_lapse_id', $lapse->id)
                ->select('course_id', 'section_id')
                ->distinct()
                ->get()
                ->count()
            : 0;

        $totalSections = DB::table('course_sections')
            ->select('course_id', 'section_id')
            ->distinct()
            ->get()
            ->count();

        return [
            'with_schedule' => (int) $withSchedule,
            'total_sections' => (int) $totalSections,
        ];
    }

    /**
     * Período escolar activo (texto, sin dinero).
     */
    private function activePeriod(): ?array
    {
        $lapse = SchoolLapse::where('status', 1)->with('lapses')->first();

        if (! $lapse) {
            return null;
        }

        return [
            'label' => Carbon::parse($lapse->start)->year.' - '.Carbon::parse($lapse->end)->year,
            'moments' => $lapse->lapses->count(),
        ];
    }

    /**
     * Cantidad de pagos registrados (no borrados) dentro del período.
     */
    private function paymentsCount(?SchoolLapse $lapse): int
    {
        return Payment::where('status', '!=', 0)
            ->when($lapse, fn ($q) => $q->whereBetween('date', [$lapse->start, $lapse->end]))
            ->count();
    }

    /**
     * Conteo de planes de evaluación por estado dentro del período.
     */
    private function plansSummary(?SchoolLapse $lapse): array
    {
        $counts = EvaluationPlan::query()
            ->when($lapse, fn ($q) => $q->where('school_lapse_id', $lapse->id))
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $approved = (int) ($counts['approved'] ?? 0);
        $pending = (int) ($counts['pending'] ?? 0);
        $rejected = (int) ($counts['rejected'] ?? 0);
        $draft = (int) ($counts['draft'] ?? 0);

        return [
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'draft' => $draft,
            'total' => $approved + $pending + $rejected + $draft,
        ];
    }

    /**
     * Matrícula del período vs. el período inmediatamente anterior.
     * Se cuenta un alumno por período usando los balances generados.
     */
    private function enrollmentVariation(?SchoolLapse $lapse): array
    {
        if (! $lapse) {
            return ['current' => 0, 'previous' => 0, 'variation' => 0, 'percentage' => 0.0];
        }

        $current = BalanceStudent::where('school_lapse_id', $lapse->id)
            ->whereHas('student', fn ($q) => $q->where('status', '!=', 0))
            ->distinct('student_id')
            ->count('student_id');

        $previousLapse = SchoolLapse::where('id', '!=', $lapse->id)
            ->where('start', '<', $lapse->start)
            ->orderByDesc('start')
            ->first();

        $previous = $previousLapse
            ? BalanceStudent::where('school_lapse_id', $previousLapse->id)
                ->distinct('student_id')
                ->count('student_id')
            : 0;

        $variation = $current - $previous;
        $percentage = $previous > 0 ? round(($variation / $previous) * 100, 1) : 0.0;

        return [
            'current' => $current,
            'previous' => $previous,
            'variation' => $variation,
            'percentage' => $percentage,
        ];
    }

    /**
     * Cantidad de representantes (distintos) del período vs. el anterior.
     * Se cuentan los representantes de los alumnos con balance en cada período.
     */
    private function representativeVariation(?SchoolLapse $lapse): array
    {
        if (! $lapse) {
            return ['current' => 0, 'previous' => 0, 'variation' => 0, 'percentage' => 0.0];
        }

        $current = $this->representativesForLapse($lapse->id);

        $previousLapse = SchoolLapse::where('id', '!=', $lapse->id)
            ->where('start', '<', $lapse->start)
            ->orderByDesc('start')
            ->first();

        $previous = $previousLapse ? $this->representativesForLapse($previousLapse->id) : 0;

        $variation = $current - $previous;
        $percentage = $previous > 0 ? round(($variation / $previous) * 100, 1) : 0.0;

        return [
            'current' => $current,
            'previous' => $previous,
            'variation' => $variation,
            'percentage' => $percentage,
        ];
    }

    private function representativesForLapse(int $lapseId): int
    {
        return (int) BalanceStudent::where('balance_students.school_lapse_id', $lapseId)
            ->join('students', 'balance_students.student_id', '=', 'students.id')
            ->where('students.status', '!=', 0)
            ->whereNotNull('students.representative_id')
            ->distinct('students.representative_id')
            ->count('students.representative_id');
    }

    /**
     * Deuda vencida (inscripción + meses con status debt/partially_paid) del
     * período. Una sola consulta por balances, sin cargar la relación student.
     */
    private function outstandingDebt(?SchoolLapse $lapse): float
    {
        if (! $lapse) {
            return 0.0;
        }

        return (float) $this->balancesFor($lapse)
            ->sum(fn (BalanceStudent $balance) => $balance->currentDebt());
    }

    /**
     * Monto efectivamente cobrado e imputado a balances del período.
     */
    private function collectedForLapse(?SchoolLapse $lapse): float
    {
        if (! $lapse) {
            return 0.0;
        }

        return (float) BalancePayment::whereHas('balanceStudent', function ($q) use ($lapse) {
            $q->where('school_lapse_id', $lapse->id);
        })
            ->whereHas('payment', fn ($q) => $q->where('status', '!=', 0))
            ->sum('amount');
    }

    /**
     * % de representantes "al día": todos sus alumnos activos sin deuda vencida.
     */
    private function representativesUpToDate(?SchoolLapse $lapse): array
    {
        if (! $lapse) {
            return ['total' => 0, 'up_to_date' => 0];
        }

        $balances = $this->balancesFor($lapse);

        if ($balances->isEmpty()) {
            return ['total' => 0, 'up_to_date' => 0];
        }

        $studentIds = $balances->pluck('student_id')->unique()->values();

        $repByStudent = Student::whereIn('id', $studentIds)
            ->get(['id', 'representative_id'])
            ->keyBy('id');

        $repsWithDebt = [];

        foreach ($balances as $balance) {
            if ($balance->currentDebt() <= 0) {
                continue;
            }

            $repId = $repByStudent->get($balance->student_id)?->representative_id;
            if ($repId) {
                $repsWithDebt[$repId] = true;
            }
        }

        $totalReps = $repByStudent
            ->pluck('representative_id')
            ->filter()
            ->unique()
            ->count();

        return [
            'total' => $totalReps,
            'up_to_date' => $totalReps - count($repsWithDebt),
        ];
    }

    /**
     * Meta de ingresos del mes en curso: mensualidad efectiva de cada alumno
     * activo (con exención) + inscripciones cuando el mes es septiembre.
     */
    private function monthTarget(?SchoolLapse $lapse, float $monthlyPrice): float
    {
        if (! $lapse || $monthlyPrice <= 0) {
            return 0.0;
        }

        $balances = $this->balancesFor($lapse);
        $studentIds = $balances->pluck('student_id')->unique()->values();

        $multipliers = Student::whereIn('id', $studentIds)
            ->get(['id', 'is_exempt', 'exemption_percentage'])
            ->mapWithKeys(function (Student $student) {
                $multiplier = $student->is_exempt
                    ? (1 - (($student->exemption_percentage ?? 0) / 100))
                    : 1;

                return [$student->id => max(0, $multiplier)];
            });

        $target = $balances->sum(fn (BalanceStudent $balance) => $monthlyPrice * ($multipliers[$balance->student_id] ?? 1));

        $currentMonthIndex = $this->currentMonthIndex();

        if ($currentMonthIndex === 0) {
            $target += $this->expectedInscriptions($lapse);
        }

        return $target;
    }

    private function expectedInscriptions(?SchoolLapse $lapse): float
    {
        if (! $lapse) {
            return 0.0;
        }

        $remaining = (float) BalanceStudent::where('school_lapse_id', $lapse->id)
            ->where('inscription', '<', 0)
            ->sum('inscription');

        $paid = (float) BalancePayment::whereHas('balanceStudent', function ($q) use ($lapse) {
            $q->where('school_lapse_id', $lapse->id);
        })
            ->where('is_inscription', true)
            ->sum('amount');

        return abs($remaining) + $paid;
    }

    private function monthIncome(?SchoolLapse $lapse): float
    {
        $moment = $this->currentMoment($lapse);

        if (! $moment) {
            return 0.0;
        }

        return (float) Payment::where('status', '!=', 0)
            ->whereBetween('date', [$moment->start, $moment->end])
            ->sum('total_in_dolars');
    }

    private function currentMoment(?SchoolLapse $lapse)
    {
        if (! $lapse) {
            return null;
        }

        $now = Carbon::now();

        return $lapse->lapses->first(fn ($m) => $now->between($m->start, $m->end))
            ?? $lapse->lapses->sortByDesc('number')->first();
    }

    private function currentMonthIndex(): int
    {
        $index = array_search(strtolower(Carbon::now()->englishMonth), self::MONTH_ORDER, true);

        return $index === false ? 0 : $index;
    }

    /**
     * Balances del período de alumnos activos, con sólo las columnas usadas
     * por currentDebt() para no hidratar la relación student.
     */
    private function balancesFor(SchoolLapse $lapse)
    {
        $columns = ['id', 'student_id', 'school_lapse_id', 'inscription', 'inscription_status'];

        foreach (self::MONTH_ORDER as $month) {
            $columns[] = $month;
            $columns[] = $month.'_status';
        }

        return BalanceStudent::where('school_lapse_id', $lapse->id)
            ->whereHas('student', fn ($q) => $q->where('status', '!=', 0))
            ->get($columns);
    }
}
