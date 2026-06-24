<?php

namespace App\Enums;

enum UserTypeEnum: int
{
    case Administrator = 1;
    case Representative = 2;
    case Teacher = 3;

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrador',
            self::Representative => 'Representante',
            self::Teacher => 'Profesor',
        };
    }
}
