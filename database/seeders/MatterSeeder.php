<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MatterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $matters = [
            'Matemática',
            'Lengua y Literatura',
            'Ciencias Naturales',
            'Ciencias Sociales',
            'Inglés',
            'Educación Física',
            'Arte y Patrimonio',
            'Informática',
            'Física',
            'Química',
            'Biología',
            'Castellano',
        ];

        $existing = DB::table('matters')->pluck('name')->map(fn ($name) => strtolower($name));

        $toInsert = [];
        foreach ($matters as $name) {
            if (! $existing->contains(strtolower($name))) {
                $toInsert[] = ['name' => $name];
            }
        }

        if (! empty($toInsert)) {
            DB::table('matters')->insert($toInsert);
        }
    }
}
