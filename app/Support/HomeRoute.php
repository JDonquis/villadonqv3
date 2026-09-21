<?php

namespace App\Support;

use App\Enums\UserTypeEnum;
use App\Models\User;

class HomeRoute
{
    public static function forUser(?User $user): string
    {
        return match ($user?->type_user_id) {
            UserTypeEnum::Representative->value => '/dashboard/mis-hijos',
            UserTypeEnum::Teacher->value => '/dashboard/mis-planes',
            default => '/dashboard',
        };
    }
}