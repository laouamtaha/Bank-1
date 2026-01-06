<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Encryption;

use Ritechoice23\ChatEngine\Contracts\EncryptionDriver;

/**
 * Null encryption driver - no encryption applied.
 *
 * Default driver when encryption is disabled.
 * Payloads are JSON encoded but not encrypted.
 */
class NullDriver implements EncryptionDriver
{
    public function encrypt(array $payload, array $context = []): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }

    public function decrypt(string $encrypted, array $context = []): array
    {
        return json_decode($encrypted, true, 512, JSON_THROW_ON_ERROR);
    }

    public function getDriverName(): string
    {
        return 'none';
    }

    public function canDecrypt(string $driverName): bool
    {
        return $driverName === 'none' || $driverName === '';
    }
}
