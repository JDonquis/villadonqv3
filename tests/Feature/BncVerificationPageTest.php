<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BncVerificationPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_open_bnc_verification_page(): void
    {
        DB::table('type_users')->insertOrIgnore([
            ['id' => 1, 'name' => 'Administrador'],
            ['id' => 2, 'name' => 'Representante'],
            ['id' => 3, 'name' => 'Profesor'],
        ]);

        $admin = User::create([
            'type_user_id' => 1,
            'ci' => 'BNCADMIN'.random_int(1000, 9999),
            'name' => 'Admin',
            'last_name' => 'BNC',
            'email' => 'bncadmin'.random_int(1000, 9999).'@example.test',
            'password' => bcrypt('secret'),
            'is_admin' => true,
        ]);

        $this->actingAs($admin)
            ->get('/dashboard/pagos/verificaciones-bnc')
            ->assertOk();
    }
}
