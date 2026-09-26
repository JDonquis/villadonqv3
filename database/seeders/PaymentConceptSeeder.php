<?php

namespace Database\Seeders;

use App\Services\StudentChargeService;
use Illuminate\Database\Seeder;

class PaymentConceptSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        (new StudentChargeService)->syncConcepts();
    }
}
