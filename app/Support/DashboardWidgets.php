<?php

namespace App\Support;

use App\Models\User;

/**
 * Registro único de widgets del dashboard y su módulo requerido.
 * Es la fuente de verdad para decidir qué ve cada usuario.
 */
class DashboardWidgets
{
    /**
     * clave => ['module' => slug, 'money' => bool]
     */
    public const WIDGETS = [
        // Matrícula
        'kpi_enrollment' => ['module' => 'matricula', 'money' => false],
        'kpi_representatives' => ['module' => 'matricula', 'money' => false],
        'stat_withdrawn' => ['module' => 'matricula', 'money' => false],
        'stat_graduated' => ['module' => 'matricula', 'money' => false],

        // Pagos (recaudación)
        'kpi_payments_count' => ['module' => 'pagos', 'money' => true],
        'kpi_month_income' => ['module' => 'pagos', 'money' => true],
        'chart_collection_by_channel' => ['module' => 'pagos', 'money' => true],
        // Sólo administrador total (is_admin).
        'chart_projection_vs_real' => ['module' => 'pagos', 'money' => true, 'admin_only' => true],
        'chart_revenue_trend' => ['module' => 'pagos', 'money' => true, 'admin_only' => true],

        // Estados de cuenta (deuda / morosidad)
        'kpi_total_debt' => ['module' => 'estados-cuenta', 'money' => true],
        'kpi_reps_on_track' => ['module' => 'estados-cuenta', 'money' => true],
        'chart_debt_by_course' => ['module' => 'estados-cuenta', 'money' => true],
        'chart_aging' => ['module' => 'estados-cuenta', 'money' => true],
        'chart_top_debtors' => ['module' => 'estados-cuenta', 'money' => true],

        // Notas
        'stat_attendance_today' => ['module' => 'notas', 'money' => false],

        // Planes de evaluación
        'kpi_plans_approved' => ['module' => 'planes-evaluacion', 'money' => false],
        'kpi_plans_pending' => ['module' => 'planes-evaluacion', 'money' => false],
        'kpi_plans_rejected' => ['module' => 'planes-evaluacion', 'money' => false],

        // Módulos institucionales
        'kpi_staff_total' => ['module' => 'personal', 'money' => false],
        'kpi_teachers_total' => ['module' => 'profesores', 'money' => false],
        'kpi_matters_total' => ['module' => 'materias', 'money' => false],
        'kpi_schedules' => ['module' => 'horarios', 'money' => false],
        'kpi_active_period' => ['module' => 'configuracion', 'money' => false],
    ];

    /**
     * Claves habilitadas para un usuario.
     * - Administrador total (`is_admin = 1`): todos.
     * - Administrador limitado: los widgets de los módulos que tenga asignados
     *   (incluidos los de dinero), excepto los marcados `admin_only`.
     * - Cualquier otro rol: ninguno.
     *
     * @return array<int, string>
     */
    public static function enabledFor(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if ($user->is_admin) {
            return array_keys(self::WIDGETS);
        }

        $modules = $user->modules()->pluck('slug')->all();

        return array_values(array_filter(array_keys(self::WIDGETS), function ($key) use ($modules) {
            if (! empty(self::WIDGETS[$key]['admin_only'])) {
                return false;
            }

            return in_array(self::WIDGETS[$key]['module'], $modules, true);
        }));
    }

    public static function enabled(?User $user, string $key): bool
    {
        return in_array($key, self::enabledFor($user), true);
    }

    /**
     * ¿Puede el usuario acceder a un módulo dentro del dashboard?
     * Administrador total: todos; el resto, sólo los módulos asignados.
     */
    public static function moduleAllowed(?User $user, string $module): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->is_admin) {
            return true;
        }

        return in_array($module, $user->modules()->pluck('slug')->all(), true);
    }

    /**
     * @return array<int, string>
     */
    public static function money(): array
    {
        return array_keys(array_filter(self::WIDGETS, fn ($w) => $w['money']));
    }
}
