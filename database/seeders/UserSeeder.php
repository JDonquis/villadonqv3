<?php

namespace Database\Seeders;

use App\Enums\UserTypeEnum;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run()
    {
        User::create([
            'type_user_id' => UserTypeEnum::Administrator->value,
            'ci' => '12345678',
            'name' => 'YAKNERY ',
            'last_name' => 'HANSEN',
            'email' => 'test@example.com',
            'password' => Hash::make('12345678'),
            'phone_number' => '',
            'address' => '',
            'photo' => '',
            'is_admin' => true,
        ]);

        User::create([
            'type_user_id' => UserTypeEnum::Administrator->value,
            'ci' => '87654321',
            'name' => 'LISMARY',
            'last_name' => 'MIRANDA',
            'email' => 'test2@example.com',
            'password' => Hash::make('12345678'),
            'phone_number' => '04125551234',
            'address' => '',
            'photo' => '',
            'is_admin' => false,
        ]);
    }
}
