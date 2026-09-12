<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanDeEstudioCodeSeeder extends Seeder
{
    public function run()
    {
        $byLevel = [
            'Grado' => '31011',
            'Año' => '31022',
        ];

        $assign = function (string $keyword, string $code) {
            DB::table('courses')
                ->whereNull('plan_de_estudio_code')
                ->where('name', 'like', "%{$keyword}%")
                ->update(['plan_de_estudio_code' => $code]);
        };

        foreach ($byLevel as $keyword => $code) {
            $assign($keyword, $code);
        }
    }
}