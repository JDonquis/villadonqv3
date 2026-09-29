<?php

namespace App\Support;

use App\Enums\UserTypeEnum;
use App\Models\EvaluationPlan;
use App\Models\User;

/**
 * Única fuente de verdad para "quién puede calificar/publicar un plan de evaluación".
 *
 * No existe una tabla `teachers`: un profesor es un `users` con type_user_id = 3 y
 * la propiedad de un plan es `evaluation_plans.user_id`. Esta clase evita repetir
 * el chequeo `plan.user_id === auth()->id()` en cada endpoint (y sus huecos).
 */
class GradeAccess
{
    /**
     * Slug del módulo de Personal que habilita a la administración a calificar.
     */
    public const MODULE = 'notas';

    /**
     * ¿El usuario puede entrar a la matriz de calificaciones?
     * - Profesor: sí (solo sobre sus propios planes).
     * - Administrador: sí si es administrador completo o tiene el módulo "notas".
     * - Representante u otro: no.
     */
    public static function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isTeacher()) {
            return true;
        }

        if ((int) $user->type_user_id !== UserTypeEnum::Administrator->value) {
            return false;
        }

        return (bool) $user->is_admin
            || $user->modules()->where('slug', self::MODULE)->exists();
    }

    /**
     * ¿El usuario puede calificar/publicar este plan concreto?
     *
     * El profesor solo puede sobre planes propios. La administración puede sobre
     * cualquier plan, en nombre del profesor que lo creó.
     */
    public static function canManagePlan(?User $user, ?EvaluationPlan $plan): bool
    {
        if (! self::canManage($user)) {
            return false;
        }

        if ($user->isTeacher()) {
            return $plan !== null && (int) $plan->user_id === (int) $user->id;
        }

        return $plan !== null;
    }

    /**
     * ¿El usuario está operando sobre planes ajenos (es decir, es administración)?
     * Lo usa la UI para indicar "en nombre de {profesor}".
     */
    public static function managesAllPlans(?User $user): bool
    {
        return self::canManage($user) && $user->isTeacher() === false;
    }
}
