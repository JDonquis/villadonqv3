<?php

namespace Tests\Feature;

use App\Services\Bnc\BncClient;
use App\Services\Bnc\BncCrypto;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BncClientTest extends TestCase
{
    private BncCrypto $crypto;

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
            'services.bnc.timeout' => 5,
            'services.bnc.verify_ssl' => true,
        ]);

        Cache::flush();
    }

    public function test_log_on_caches_working_key_and_decrypts_response(): void
    {
        $workingKey = 'working-key-123';
        $json = json_encode(['WorkingKey' => $workingKey]);

        Http::fake([
            'https://bnc.test/api/Auth/LogOn' => Http::response([
                'status' => 'OK',
                'message' => '000000Login ok',
                'value' => $this->crypto->encrypt($json, 'master-test'),
                'validation' => $this->crypto->hash($json),
            ]),
        ]);

        $client = new BncClient;

        $this->assertSame($workingKey, $client->logOn(true));
        $this->assertSame($workingKey, $client->logOn());
        $this->assertTrue($client->hasCachedWorkingKey());
        $this->assertNotNull($client->lastLogOnAt());

        Http::assertSentCount(1);
    }

    public function test_validate_sends_encrypted_wrapper_and_decrypts_movement(): void
    {
        $workingKey = 'working-key-123';
        $logonJson = json_encode(['WorkingKey' => $workingKey]);
        $movementJson = json_encode([
            'MovementExists' => true,
            'Amount' => 10.5,
            'ControlNumber' => '123456789',
            'ReferenceA' => '987654',
            'Type' => 'TRF',
        ]);

        Http::fake([
            'https://bnc.test/api/Auth/LogOn' => Http::response([
                'status' => 'OK',
                'message' => '000000Login ok',
                'value' => $this->crypto->encrypt($logonJson, 'master-test'),
                'validation' => $this->crypto->hash($logonJson),
            ]),
            'https://bnc.test/api/Position/Validate' => Http::response([
                'status' => 'OK',
                'message' => '000000Consulta exitosa',
                'value' => $this->crypto->encrypt($movementJson, $workingKey),
                'validation' => $this->crypto->hash($movementJson),
            ]),
        ]);

        $client = new BncClient;
        $result = $client->validate([
            'ClientID' => 'J000000001',
            'AccountNumber' => '01910000000000000001',
            'Reference' => '987654',
            'Amount' => 10.50,
            'DateMovement' => '2026-11-02',
            'ChildClientID' => '',
            'BranchID' => '',
        ]);

        $this->assertTrue($result['data']['MovementExists']);
        $this->assertSame('123456789', $result['data']['ControlNumber']);

        Http::assertSent(function (Request $request) use ($workingKey) {
            if (! str_ends_with($request->url(), '/api/Position/Validate')) {
                return false;
            }

            $body = $request->data();

            if (($body['ClientGUID'] ?? null) !== 'GUID-TEST' || empty($body['Reference'])) {
                return false;
            }

            $this->assertArrayHasKey('swTestOperation', $body);

            $decrypted = $this->crypto->decrypt($body['Value'], $workingKey);

            return $this->crypto->verifyHash($decrypted, $body['Validation'])
                && json_decode($decrypted, true)['Reference'] === '987654';
        });
    }

    public function test_refreshes_working_key_after_security_error(): void
    {
        $workingKey = 'working-key-123';
        $logonJson = json_encode(['WorkingKey' => $workingKey]);
        $movementJson = json_encode(['MovementExists' => true, 'Amount' => 10.5, 'ReferenceA' => '987654']);
        $validateCalls = 0;

        Http::fake(function (Request $request) use (&$validateCalls, $workingKey, $logonJson, $movementJson) {
            if (str_ends_with($request->url(), '/api/Auth/LogOn')) {
                return Http::response([
                    'status' => 'OK',
                    'message' => '000000Login ok',
                    'value' => $this->crypto->encrypt($logonJson, 'master-test'),
                    'validation' => $this->crypto->hash($logonJson),
                ]);
            }

            $validateCalls++;

            if ($validateCalls === 1) {
                return Http::response([
                    'status' => 'KO',
                    'message' => 'EPIRWK Petición denegada por medidas de seguridad',
                ]);
            }

            return Http::response([
                'status' => 'OK',
                'message' => '000000Consulta exitosa',
                'value' => $this->crypto->encrypt($movementJson, $workingKey),
                'validation' => $this->crypto->hash($movementJson),
            ]);
        });

        $client = new BncClient;
        $result = $client->validate([
            'ClientID' => 'J000000001',
            'AccountNumber' => '01910000000000000001',
            'Reference' => '987654',
            'Amount' => 10.50,
            'DateMovement' => '2026-11-02',
        ]);

        $this->assertTrue($result['data']['MovementExists']);
        $this->assertSame(2, $validateCalls);
    }
}
