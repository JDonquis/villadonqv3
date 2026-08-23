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
            'ci' => '30847627',
            'name' => 'Juan ',
            'last_name' => 'Donquis',
            'email' => 'juandonquis07@gmail.com',
            'password' => Hash::make('12345678'),
            'phone_number' => '',
            'address' => '',
            'photo' => '',
            'is_admin' => true,
        ]);

        User::create([
            'type_user_id' => UserTypeEnum::Administrator->value,
            'ci' => '27253194',
            'name' => 'Juan',
            'last_name' => 'Villasmil',
            'email' => 'juanvillans16@gmail.com',
            'password' => Hash::make('12345678'),
            'phone_number' => '',
            'address' => '',
            'photo' => '',
            'is_admin' => true,
        ]);
    }
}
