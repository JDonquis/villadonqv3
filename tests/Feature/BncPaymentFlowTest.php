<?php

namespace Tests\Feature;

use App\Models\AccountPayment;
use App\Models\BalanceStudent;
use App\Models\BncNotification;
use App\Models\BncPaymentRequest;
use App\Models\Course;
use App\Models\MainConfig;
use App\Models\Payment;
use App\Models\Representative;
use App\Models\SchoolLapse;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Services\Bnc\BncCrypto;
use App\Services\Bnc\BncPaymentRequestService;
use App\Services\Bnc\BncPaymentVerifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BncPaymentFlowTest extends TestCase
{
    use DatabaseTransactions;

    private BncCrypto $crypto;

    private string $workingKey = 'working-key-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->crypto = new BncCrypto;

        config([
            'services.bnc.enabled' => true,
            'services.bnc.base_url' => 'https://bnc.test',
            'services.bnc.client_guid' => 'GUID-TEST',
            'services.bnc.master_key' => 'master-test',
            'services.bnc.client_id' => 'J000000001',
            'services.bnc.account_number' => '01910000000000000001',
            'services.bnc.currency' => 'VES',
            'services.bnc.require_reference' => true,
            'services.bnc.timeout' => 5,
        ]);

        Cache::flush();
    }

    public function test_representative_payment_stays_pending_then_webhook_verifies_it(): void
    {
        [$account, $student, $balance, $rep] = $this->scenario();

        $reference = (string) random_int(100000, 9999999);
        $payload = $this->paymentPayload($account, $student, $balance, $reference);

        $this->fakeBnc(notFound: true);

        $requestService = new BncPaymentRequestService;
        $verifier = new BncPaymentVerifier;

        $request = $requestService->create($payload, $account, $rep, [$student->id]);
        $request = $verifier->verify($request);

        $this->assertSame(BncPaymentRequest::STATUS_PENDING, $request->status);
        $this->assertFalse(Payment::where('reference', $reference)->where('status', '!=', 0)->exists());

        $notification = BncNotification::create([
            'hash' => BncNotification::hashFor(['ref' => $reference, 'amount' => 1000]),
            'payment_type' => 'TRF',
            'origin_bank_reference' => $reference,
            'destiny_bank_reference' => 'DEST-'.$reference,
            'origin_bank_code' => '0102',
            'amount' => 1000,
            'currency_code' => '0928',
            'transaction_date' => now()->toDateString(),
            'commerce_id' => 'J000000001',
            'creditor_account' => '01910000000000000001',
            'debtor_account' => '01020000000000000001',
            'debtor_id' => 'V12345678',
            'concept' => 'Prueba',
            'raw_payload' => ['ref' => $reference, 'amount' => 1000],
            'status' => BncNotification::STATUS_RECEIVED,
        ]);

        $notification = $verifier->verifyFromNotification($notification);
        $request->refresh();

        $this->assertSame(BncNotification::STATUS_PROCESSED, $notification->status);
        $this->assertSame(BncPaymentRequest::STATUS_VERIFIED, $request->status);

        $payment = Payment::where('reference', $reference)->where('status', '!=', 0)->first();

        $this->assertNotNull($payment);
        $this->assertSame('verified', $payment->verification_status);
        $this->assertSame('bnc_webhook', $payment->verification_source);

        $balance->refresh();
        $this->assertGreaterThan(-10, (float) $balance->september);
    }

    private function scenario(): array
    {
        DB::table('type_users')->insertOrIgnore([
            ['id' => 1, 'name' => 'Administrador'],
            ['id' => 2, 'name' => 'Representante'],
            ['id' => 3, 'name' => 'Profesor'],
        ]);

        $mainConfig = MainConfig::first() ?: MainConfig::create([
            'name' => 'Colegio Test',
            'rif' => 'J000000001',
            'phone_number' => '04140000000',
            'address' => 'Dirección test',
            'email' => 'test@example.com',
            'release' => '2000-01-01',
            'motto' => 'Motto test',
            'regular_inscription_price' => 10,
            'new_inscription_price' => 10,
            'monthly_payment' => 10,
            'day_of_monthly_payment' => 1,
            'grace_period' => 0,
        ]);

        $methodId = DB::table('payment_methods')->where('name', 'Pago Movil')->value('id');

        if (! $methodId) {
            $methodId = DB::table('payment_methods')->insertGetId(['name' => 'Pago Movil']);
        }

        $account = AccountPayment::create([
            'payment_method_id' => $methodId,
            'bank' => 'BNC',
            'account_number' => '01910000000000000001',
            'status' => 1,
        ]);

        $course = Course::firstOrCreate(['name' => 'Curso Test BNC']);
        $section = Section::firstOrCreate(['name' => 'BNCT']);

        $lapse = SchoolLapse::create([
            'start' => now()->subYear()->toDateString(),
            'end' => now()->toDateString(),
            'status' => 1,
        ]);

        $rep = User::create([
            'type_user_id' => 2,
            'ci' => 'BNCREP'.random_int(1000, 9999),
            'name' => 'Rep',
            'last_name' => 'Test',
            'email' => 'rep'.random_int(1000, 9999).'@example.test',
            'password' => bcrypt('secret'),
        ]);

        $representative = Representative::create([
            'user_id' => $rep->id,
            'profession' => 'Docente',
            'workplace' => 'Colegio',
            'relationship' => 'Madre',
        ]);

        $student = Student::create([
            'representative_id' => $representative->id,
            'course_id' => $course->id,
            'section_id' => $section->id,
            'name' => 'Alumno',
            'last_name' => 'Test',
            'date_birth' => '2010-01-01',
            'ci' => 'BNCSTU'.random_int(1000, 9999),
            'status' => 1,
        ]);

        $months = [];
        foreach (BalanceStudent::MONTHS as $month) {
            $months[$month] = $month === 'september' ? -10 : 0;
            $months[$month.'_status'] = $month === 'september' ? 'debt' : 'paid';
        }

        $balance = BalanceStudent::create(array_merge([
            'student_id' => $student->id,
            'school_lapse_id' => $lapse->id,
            'status' => 'debt',
            'inscription' => 0,
            'inscription_status' => 'paid',
            'start_month' => 'september',
        ], $months));

        return [$account, $student, $balance, $rep];
    }

    private function paymentPayload(AccountPayment $account, Student $student, BalanceStudent $balance, string $reference): array
    {
        return [
            'date' => now()->toDateString(),
            'reported_date' => now()->toDateString(),
            'account_payment_id' => $account->id,
            'payment_concept_id' => null,
            'total_in_dolars' => 10,
            'total_in_bs' => 1000,
            'exchange_rate' => 100,
            'reference' => $reference,
            'observations' => 'Prueba BNC',
            'students' => [[
                'id' => $student->id,
                'amount_in_dolars' => 10,
                'regular_amount' => 10,
                'balances' => [['id' => $balance->id]],
            ]],
            'concepts' => [],
        ];
    }

    private function fakeBnc(bool $notFound): void
    {
        $logonJson = json_encode(['WorkingKey' => $this->workingKey]);
        $movement = $notFound
            ? ['MovementExists' => false, 'Amount' => 0, 'ReferenceA' => '']
            : ['MovementExists' => true, 'Amount' => 1000, 'ReferenceA' => 'BNCTEST'];
        $movementJson = json_encode($movement);

        Http::fake(function ($request) use ($logonJson, $movementJson) {
            if (str_ends_with($request->url(), '/api/Auth/LogOn')) {
                return Http::response([
                    'status' => 'OK',
                    'message' => '000000Login ok',
                    'value' => $this->crypto->encrypt($logonJson, 'master-test'),
                    'validation' => $this->crypto->hash($logonJson),
                ]);
            }

            return Http::response([
                'status' => 'OK',
                'message' => '000000Consulta exitosa',
                'value' => $this->crypto->encrypt($movementJson, $this->workingKey),
                'validation' => $this->crypto->hash($movementJson),
            ]);
        });
    }
}
