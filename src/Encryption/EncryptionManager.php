<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Encryption;

use InvalidArgumentException;
use Ritechoice23\ChatEngine\Contracts\EncryptionDriver;

/**
 * Manages encryption drivers and operations.
 */
class EncryptionManager
{
    /**
     * @var array<string, EncryptionDriver>
     */
    protected array $drivers = [];

    protected ?string $defaultDriver = null;

    public function __construct()
    {
        // Register built-in drivers
        $this->registerDriver(new NullDriver);
        $this->registerDriver(new LaravelDriver);

        // Set default from config
        $this->defaultDriver = config('chat-engine.encryption.driver', 'none');
    }

    /**
     * Register an encryption driver.
     */
    public function registerDriver(EncryptionDriver $driver): self
    {
        $this->drivers[$driver->getDriverName()] = $driver;

        return $this;
    }

    /**
     * Get a driver by name.
     */
    public function driver(?string $name = null): EncryptionDriver
    {
        $name = $name ?? $this->defaultDriver ?? 'none';

        if (! isset($this->drivers[$name])) {
            throw new InvalidArgumentException("Encryption driver [{$name}] not registered.");
        }

        return $this->drivers[$name];
    }

    /**
     * Get the default driver.
     */
    public function getDefaultDriver(): EncryptionDriver
    {
        return $this->driver($this->defaultDriver);
    }

    /**
     * Check if encryption is enabled.
     */
    public function isEnabled(): bool
    {
        return config('chat-engine.encryption.enabled', false) === true;
    }

    /**
     * Encrypt a payload using the default driver.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     */
    public function encrypt(array $payload, array $context = []): string
    {
        if (! $this->isEnabled()) {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        }

        return $this->getDefaultDriver()->encrypt($payload, $context);
    }

    /**
     * Decrypt a payload.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function decrypt(string $encrypted, ?string $driverName = null, array $context = []): array
    {
        // If not encrypted or null driver, just decode JSON
        if (! $this->isEnabled() || $driverName === 'none' || $driverName === null) {
            return json_decode($encrypted, true, 512, JSON_THROW_ON_ERROR);
        }

        // Find driver that can decrypt
        foreach ($this->drivers as $driver) {
            if ($driver->canDecrypt($driverName)) {
                return $driver->decrypt($encrypted, $context);
            }
        }

        throw new InvalidArgumentException("No driver can decrypt payload encrypted with [{$driverName}].");
    }

    /**
     * Get the current driver name.
     */
    public function getDriverName(): string
    {
        return $this->getDefaultDriver()->getDriverName();
    }

    /**
     * Get all registered driver names.
     *
     * @return array<string>
     */
    public function getRegisteredDrivers(): array
    {
        return array_keys($this->drivers);
    }
}
