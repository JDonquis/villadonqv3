<?php

namespace Tests\Feature;

use App\Jobs\ProcessBncNotification;
use App\Models\BncNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BncWebhookTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bnc.enabled' => true,
            'services.bnc.webhook.auth' => 'api_key',
            'services.bnc.webhook.api_key' => 'test-api-key',
            'services.bnc.webhook.login' => 'test-login',
            'services.bnc.webhook.password' => 'test-password',
        ]);
    }

    public function test_ping_responds_ok_without_auth(): void
    {
        $this->getJson('/api/bnc/notifications')
            ->assertOk()
            ->assertJson(['status' => 'OK']);
    }

    public function test_rejects_invalid_api_key(): void
    {
        $this->postJson('/api/bnc/notifications', $this->payload(), ['x-api-key' => 'bad'])
            ->assertStatus(401);
    }

    public function test_stores_notification_and_is_idempotent(): void
    {
        Queue::fake();

        $reference = 'REF-'.uniqid();
        $payload = $this->payload($reference);

        $this->postJson('/api/bnc/notifications', $payload, ['x-api-key' => 'test-api-key'])
            ->assertOk()
            ->assertJson(['status' => 'OK']);

        $this->assertSame(1, BncNotification::where('origin_bank_reference', $reference)->count());
        Queue::assertPushed(ProcessBncNotification::class);

        $this->postJson('/api/bnc/notifications', $payload, ['x-api-key' => 'test-api-key'])
            ->assertOk();

        $this->assertSame(1, BncNotification::where('origin_bank_reference', $reference)->count());
    }

    public function test_bearer_token_authentication(): void
    {
        Queue::fake();
        config(['services.bnc.webhook.auth' => 'jwt']);

        $tokenResponse = $this->postJson('/api/bnc/auth', [
            'Login' => 'test-login',
            'Password' => 'test-password',
        ])->assertOk();

        $token = trim($tokenResponse->getContent());

        $reference = 'REF-JWT-'.uniqid();

        $this->postJson('/api/bnc/notifications', $this->payload($reference), [
            'Authorization' => 'Bearer '.$token,
        ])->assertOk();

        $this->assertSame(1, BncNotification::where('origin_bank_reference', $reference)->count());
    }

    public function test_ping_post_without_notification_payload_does_not_require_auth(): void
    {
        $this->postJson('/api/bnc/notifications', [])
            ->assertOk()
            ->assertJson(['status' => 'OK']);
    }

    private function payload(string $reference = '123456'): array
    {
        return [
            'PaymentType' => 'TRF',
            'OriginBankReference' => $reference,
            'DestinyBankReference' => '999888',
            'OriginBankCode' => '0102',
            'Amount' => '1000.00',
            'CurrencyCode' => '0928',
            'Date' => '20261102',
            'Hour' => '120000',
            'CommerceID' => 'J000000001',
            'CreditorAccount' => '01910000000000000001',
            'DebtorAccount' => '01020000000000000001',
            'DebtorID' => 'V12345678',
            'Concept' => 'Prueba webhook',
        ];
    }
}
