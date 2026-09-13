<?php

namespace App\Support;

use App\Models\MainConfig;

class EducationLevel
{
    public const PREESCOLAR = 'preescolar';

    public const PRIMARIA = 'primaria';

    public const SECUNDARIA = 'secundaria';

    /**
     * Niveles educativos según los cursos del sistema:
     * - Preescolar: 1er, 2do y 3er Nivel.
     * - Primaria: 1er a 6to Grado.
     * - Secundaria: 1er a 5to Año.
     */
    public static function forCourseId(?int $courseId): string
    {
        $courseId = (int) $courseId;

        return match (true) {
            in_array($courseId, [12, 13, 14], true) => self::PREESCOLAR,
            in_array($courseId, [6, 7, 8, 9, 10, 11], true) => self::PRIMARIA,
            default => self::SECUNDARIA,
        };
    }

    public static function label(string $level): string
    {
        return match ($level) {
            self::PREESCOLAR => 'Preescolar',
            self::PRIMARIA => 'Primaria',
            default => 'Secundaria',
        };
    }

    /**
     * Precio de inscripción/reinscripción según el nivel del curso. Si el campo
     * del nivel es null, cae al precio general (new_inscription_price).
     */
    public static function inscriptionPrice(?MainConfig $config, ?int $courseId): float
    {
        if (! $config) {
            return 0.0;
        }

        $column = self::forCourseId($courseId).'_inscription_price';
        $value = $config->{$column};

        if ($value === null) {
            $value = $config->new_inscription_price;
        }

        return (float) $value;
    }
}
