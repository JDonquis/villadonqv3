<?php

namespace Database\Seeders;

use App\Enums\PaymentMethodEnum;
use Carbon\Carbon;
use DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AccountPaymentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rows =
            [
                ['payment_method_id' => PaymentMethodEnum::Efectivo->value, 'person_name' => null, 'email' => null, 'ci' => null, 'phone_number' => null, 'bank' => null, 'account_number' => null, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'comision' => null, 'cash_currency' => 'Bolivares'],
                ['payment_method_id' => PaymentMethodEnum::Efectivo->value, 'person_name' => null, 'email' => null, 'ci' => null, 'phone_number' => null, 'bank' => null, 'account_number' => null, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'comision' => null, 'cash_currency' => 'Dolares'],

                ['payment_method_id' => PaymentMethodEnum::PagoMovil->value, 'person_name' => null, 'email' => null, 'ci' => '10478463', 'phone_number' => '04146846012', 'bank' => 'Provincial', 'account_number' => null, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'comision' => null, 'cash_currency' => null],
                ['payment_method_id' => PaymentMethodEnum::PagoMovil->value, 'person_name' => null, 'email' => null, 'ci' => '10478463', 'phone_number' => '04146846012', 'bank' => 'BNC', 'account_number' => null, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'comision' => null, 'cash_currency' => null],
                ['payment_method_id' => PaymentMethodEnum::Transferencia->value, 'person_name' => 'COLEGIO MAESTRO JOSÉ MARTÍ', 'email' => null, 'ci' => '10478463', 'phone_number' => '04146846012', 'bank' => 'BNC', 'account_number' => '0191-0122-34-2100102346', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'comision' => null, 'cash_currency' => null],
                ['payment_method_id' => PaymentMethodEnum::Transferencia->value, 'person_name' => 'COLEGIO MAESTRO JOSÉ MARTÍ', 'email' => null, 'ci' => '10478463', 'phone_number' => '04146846012', 'bank' => 'Provincial', 'account_number' => '0105-0116-01-0000001234', 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'comision' => null, 'cash_currency' => null],
                ['payment_method_id' => PaymentMethodEnum::PuntoDeVenta->value, 'person_name' => null, 'email' => null, 'ci' => null, 'phone_number' => null, 'bank' => 'Banco de Venezuela', 'account_number' => null, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now(), 'comision' => 0.00, 'cash_currency' => null],

            ];

        DB::table('account_payments')->insert($rows);
    }
}
