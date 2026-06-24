<?php

namespace Database\Seeders;

use App\Enums\BalanceStudentStatusEnum;
use App\Models\BalanceStudent;
use App\Models\MainConfig;
use App\Models\SchoolLapse;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

        // $this->truncateTable([

        //     'students',
        //     'representatives',
        //     'payment_methods',
        //     'account_payments',
        //     'courses',
        //     'sections',
        //     'course_sections',
        //     'request_status',
        //     'type_documents',
        //     'type_users',
        //     'users',
        //     'main_configs',
        //     'school_lapses',
        //     'quotas',
        // ]);

        // $this->call([

        //     PaymentMethodSeeder::class,
        //     AccountPaymentSeeder::class,
        //     CourseSeeder::class,
        //     SectionSeeder::class,
        //     CourseSectionSeeder::class,
        //     RequestStatusSeeder::class,
        //     TypeUserSeeder::class,
        //     UserSeeder::class,
        //     TypeDocumentSeeder::class,
        //     MainConfigSeeder::class,
        //     SchoolLapseSeeder::class,
        //     QuotaSeeder::class,
        //     // StudentSeeder::class,

        // ]);

        $this->recalculateStudentsDebt();

        // SchoolLapse::where('status', 1)->update(['status' => 0]);

        // SchoolLapse::create([
        //     'start' => '2027-09-01',
        //     'end' => '2028-08-31',
        //     'status' => 1,
        // ]);

        // $this->call([QuotaSeeder::class]);
    }

    public function recalculateStudentsDebt()
    {
        $configData = MainConfig::select('new_inscription_price', 'monthly_payment')->first();
        if (!$configData) return;

        $balances = BalanceStudent::with('student')->get();
        $currentMonthLower = strtolower(Carbon::now()->englishMonth);

        $months = [
            'september',
            'october',
            'november',
            'december',
            'january',
            'february',
            'march',
            'april',
            'may',
            'june',
            'july',
            'august'
        ];

        foreach ($balances as $balance) {
            $student = $balance->student;
            $effectiveMonthlyPayment = (float) $configData->monthly_payment;
            $effectiveInscriptionPrice = (float) $configData->new_inscription_price;

            if ($student->is_exempt && $student->exemption_percentage) {
                $multiplier = 1 - ($student->exemption_percentage / 100);
                $effectiveMonthlyPayment *= $multiplier;
                $effectiveInscriptionPrice *= $multiplier;
            }

            $updateData = [
                'inscription' => -$effectiveInscriptionPrice,
                'inscription_status' => BalanceStudentStatusEnum::Debt,
                'status' => BalanceStudentStatusEnum::Debt,
            ];

            $reachedCurrent = false;
            foreach ($months as $month) {
                if (!$reachedCurrent) {
                    $updateData[$month . '_status'] = BalanceStudentStatusEnum::Debt;
                    $updateData[$month] = -$effectiveMonthlyPayment;
                    if ($month == $currentMonthLower) {
                        $reachedCurrent = true;
                    }
                } else {
                    $updateData[$month . '_status'] = BalanceStudentStatusEnum::Pending;
                    $updateData[$month] = -$effectiveMonthlyPayment;
                }
            }

            $balance->update($updateData);
        }
    }

    protected function truncateTable(array $tables)
    {

        DB::statement('SET FOREIGN_KEY_CHECKS = 0;');
        foreach ($tables as $table) {
            DB::table($table)->truncate();
        }
        DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
    }
}
