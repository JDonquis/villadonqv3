<?php

namespace Tests\Unit;

use App\Enums\UserTypeEnum;
use App\Models\User;
use App\Support\HomeRoute;
use PHPUnit\Framework\TestCase;

class HomeRouteTest extends TestCase
{
    public function test_administrator_goes_to_dashboard(): void
    {
        $user = new User;
        $user->type_user_id = UserTypeEnum::Administrator->value;

        $this->assertSame('/dashboard', HomeRoute::forUser($user));
    }

    public function test_representative_goes_to_mis_hijos(): void
    {
        $user = new User;
        $user->type_user_id = UserTypeEnum::Representative->value;

        $this->assertSame('/dashboard/mis-hijos', HomeRoute::forUser($user));
    }

    public function test_teacher_goes_to_mis_planes(): void
    {
        $user = new User;
        $user->type_user_id = UserTypeEnum::Teacher->value;

        $this->assertSame('/dashboard/mis-planes', HomeRoute::forUser($user));
    }

    public function test_no_user_goes_to_dashboard(): void
    {
        $this->assertSame('/dashboard', HomeRoute::forUser(null));
    }
}