<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Encryption;

use Illuminate\Support\Facades\Crypt;
use Ritechoice23\ChatEngine\Contracts\EncryptionDriver;

/**
 * Laravel encryption driver using the framework's Crypt facade.
 *
 * Uses Laravel's built-in encryption (AES-256-CBC by default).
 * Suitable for server-side encryption but NOT for true E2E encryption.
 *
 * For E2E encryption, implement a custom driver with client-side key management.
 */
class LaravelDriver implements EncryptionDriver
{
    public function encrypt(array $payload, array $context = []): string
    {
        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function decrypt(string $encrypted, array $context = []): array
    {
        $decrypted = Crypt::decryptString($encrypted);

        return json_decode($decrypted, true, 512, JSON_THROW_ON_ERROR);
    }

    public function getDriverName(): string
    {
        return 'laravel';
    }

    public function canDecrypt(string $driverName): bool
    {
        return $driverName === 'laravel';
    }
}
