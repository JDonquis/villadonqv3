<?php

namespace App\Services\Bnc;

use RuntimeException;

class BncCrypto
{
    private const SALT = 'Ivan Medvedev';

    private const ITERATIONS = 1000;

    private const KEY_LENGTH = 48;

    public function deriveKeyAndIv(string $secret): array
    {
        $material = hash_pbkdf2('sha1', $secret, self::SALT, self::ITERATIONS, self::KEY_LENGTH, true);

        if ($material === false || strlen($material) < self::KEY_LENGTH) {
            throw new RuntimeException('No se pudo derivar la llave de cifrado BNC.');
        }

        return [substr($material, 0, 32), substr($material, 32, 16)];
    }

    public function encrypt(string $plainText, string $secret): string
    {
        [$key, $iv] = $this->deriveKeyAndIv($secret);

        $utf16 = mb_convert_encoding($plainText, 'UTF-16LE', 'UTF-8');

        $encrypted = openssl_encrypt(
            $utf16,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new RuntimeException('No se pudo cifrar la petición BNC.');
        }

        return base64_encode($encrypted);
    }

    public function decrypt(string $encoded, string $secret): string
    {
        [$key, $iv] = $this->deriveKeyAndIv($secret);

        $raw = base64_decode($encoded, true);

        if ($raw === false) {
            throw new RuntimeException('La respuesta cifrada del BNC no es Base64 válido.');
        }

        $decrypted = openssl_decrypt(
            $raw,
            'aes-256-cbc',
            $key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new RuntimeException('No se pudo descifrar la respuesta del BNC. Verifica las credenciales.');
        }

        return mb_convert_encoding($decrypted, 'UTF-8', 'UTF-16LE');
    }

    public function hash(string $value): string
    {
        return hash('sha256', trim($value));
    }

    public function verifyHash(string $value, ?string $validation): bool
    {
        if ($validation === null || $validation === '') {
            return false;
        }

        return hash_equals(strtolower($validation), $this->hash($value));
    }
}
