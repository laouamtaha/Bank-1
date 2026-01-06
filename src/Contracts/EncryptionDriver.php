<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Contracts;

/**
 * Interface for message encryption drivers.
 *
 * Implement this interface to provide custom encryption for message payloads.
 * Encryption is opt-in and applications can integrate their own E2E solutions.
 */
interface EncryptionDriver
{
    /**
     * Encrypt a message payload.
     *
     * @param  array<string, mixed>  $payload  The message payload to encrypt
     * @param  array<string, mixed>  $context  Additional context (thread, participants, etc.)
     * @return string The encrypted payload (typically base64 encoded)
     */
    public function encrypt(array $payload, array $context = []): string;

    /**
     * Decrypt a message payload.
     *
     * @param  string  $encrypted  The encrypted payload
     * @param  array<string, mixed>  $context  Additional context for decryption
     * @return array<string, mixed> The decrypted payload
     */
    public function decrypt(string $encrypted, array $context = []): array;

    /**
     * Get the driver identifier.
     *
     * Used to track which driver encrypted a message for proper decryption.
     */
    public function getDriverName(): string;

    /**
     * Check if this driver can decrypt a payload.
     *
     * @param  string  $driverName  The driver name stored with the message
     */
    public function canDecrypt(string $driverName): bool;
}
