<?php

namespace Tests\Unit;

use App\Services\Bnc\BncCrypto;
use PHPUnit\Framework\TestCase;

class BncCryptoTest extends TestCase
{
    public function test_aes_and_hash_round_trip(): void
    {
        $crypto = new BncCrypto;
        $secret = 'clave-secreta-de-prueba';
        $plain = json_encode(['ClientGUID' => 'ABC-123', 'Monto' => 12.34, 'Texto' => 'Ñandú']);

        $encrypted = $crypto->encrypt($plain, $secret);
        $decrypted = $crypto->decrypt($encrypted, $secret);
        $hash = $crypto->hash($plain);

        $this->assertSame($plain, $decrypted);
        $this->assertSame(hash('sha256', $plain), $hash);
        $this->assertTrue($crypto->verifyHash($plain, $hash));
        $this->assertFalse($crypto->verifyHash($plain.'x', $hash));
    }

    public function test_encryption_is_deterministic_with_same_key(): void
    {
        $crypto = new BncCrypto;
        $plain = '{"a":1}';

        $this->assertSame($crypto->encrypt($plain, 'k'), $crypto->encrypt($plain, 'k'));
    }
}
