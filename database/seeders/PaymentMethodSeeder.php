<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use DB;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows = [

            ['name' => 'Efectivo'],
            ['name' => 'Pago Movil'],
            ['name' => 'Transferencia'],
            ['name' => 'Zelle'],
            ['name' => 'Binance'],
            ['name' => 'Punto de Venta'],


        ];

        DB::table('payment_methods')->insert($rows);
    }
}
