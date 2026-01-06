<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Contracts\EncryptionDriver;
use Ritechoice23\ChatEngine\Encryption\EncryptionManager;
use Ritechoice23\ChatEngine\Encryption\LaravelDriver;
use Ritechoice23\ChatEngine\Encryption\NullDriver;
use Ritechoice23\ChatEngine\Facades\Chat;
use Ritechoice23\ChatEngine\Pipes\EncryptPayload;

describe('NullDriver', function () {
    it('encodes payload as JSON without encryption', function () {
        $driver = new NullDriver;
        $payload = ['content' => 'Hello world'];

        $encrypted = $driver->encrypt($payload);
        $decrypted = $driver->decrypt($encrypted);

        expect($decrypted)->toBe($payload)
            ->and($encrypted)->toBe(json_encode($payload))
            ->and($driver->getDriverName())->toBe('none')
            ->and($driver->canDecrypt('none'))->toBeTrue()
            ->and($driver->canDecrypt(''))->toBeTrue()
            ->and($driver->canDecrypt('laravel'))->toBeFalse();
    });
});

describe('LaravelDriver', function () {
    it('encrypts and decrypts payload using Laravel Crypt', function () {
        $driver = new LaravelDriver;
        $payload = ['content' => 'Secret message', 'type' => 'text'];

        $encrypted = $driver->encrypt($payload);
        $decrypted = $driver->decrypt($encrypted);

        expect($decrypted)->toBe($payload)
            ->and($encrypted)->not->toBe(json_encode($payload))  // Should be different (encrypted)
            ->and($driver->getDriverName())->toBe('laravel')
            ->and($driver->canDecrypt('laravel'))->toBeTrue()
            ->and($driver->canDecrypt('none'))->toBeFalse();
    });
});

describe('EncryptionManager', function () {
    it('registers built-in drivers', function () {
        $manager = new EncryptionManager;

        expect($manager->getRegisteredDrivers())->toContain('none', 'laravel');
    });

    it('returns null driver by default when disabled', function () {
        config()->set('chat-engine.encryption.enabled', false);
        config()->set('chat-engine.encryption.driver', 'none');

        $manager = new EncryptionManager;

        expect($manager->isEnabled())->toBeFalse()
            ->and($manager->getDriverName())->toBe('none');
    });

    it('encrypts with configured driver when enabled', function () {
        config()->set('chat-engine.encryption.enabled', true);
        config()->set('chat-engine.encryption.driver', 'laravel');

        $manager = new EncryptionManager;
        $payload = ['content' => 'Secret'];

        $encrypted = $manager->encrypt($payload);
        $decrypted = $manager->decrypt($encrypted, 'laravel');

        expect($encrypted)->not->toBe(json_encode($payload))
            ->and($decrypted)->toBe($payload);
    });

    it('returns JSON when disabled regardless of driver', function () {
        config()->set('chat-engine.encryption.enabled', false);

        $manager = new EncryptionManager;
        $payload = ['content' => 'Hello'];

        $result = $manager->encrypt($payload);

        expect($result)->toBe(json_encode($payload));
    });

    it('allows custom driver registration', function () {
        $customDriver = new class implements EncryptionDriver
        {
            public function encrypt(array $payload, array $context = []): string
            {
                return base64_encode(json_encode($payload));
            }

            public function decrypt(string $encrypted, array $context = []): array
            {
                return json_decode(base64_decode($encrypted), true);
            }

            public function getDriverName(): string
            {
                return 'custom';
            }

            public function canDecrypt(string $driverName): bool
            {
                return $driverName === 'custom';
            }
        };

        $manager = new EncryptionManager;
        $manager->registerDriver($customDriver);

        expect($manager->getRegisteredDrivers())->toContain('custom')
            ->and($manager->driver('custom'))->toBe($customDriver);
    });

    it('throws for unknown driver', function () {
        $manager = new EncryptionManager;
        $manager->driver('unknown');
    })->throws(InvalidArgumentException::class);
});

describe('EncryptPayload Pipe', function () {
    it('skips encryption when disabled', function () {
        config()->set('chat-engine.encryption.enabled', false);

        $message = Ritechoice23\ChatEngine\Models\Message::make([
            'payload' => ['content' => 'Hello'],
            'encrypted' => false,
        ]);

        $pipe = new EncryptPayload;
        $result = $pipe->handle($message, fn ($m) => $m);

        expect($result->encrypted)->toBeFalse()
            ->and($result->payload)->toBe(['content' => 'Hello']);
    });

    it('encrypts payload when enabled', function () {
        config()->set('chat-engine.encryption.enabled', true);
        config()->set('chat-engine.encryption.driver', 'laravel');

        $message = Ritechoice23\ChatEngine\Models\Message::make([
            'thread_id' => 1,
            'sender_type' => 'user',
            'sender_id' => 1,
            'payload' => ['content' => 'Secret message'],
            'encrypted' => false,
        ]);

        $pipe = new EncryptPayload;
        $result = $pipe->handle($message, fn ($m) => $m);

        expect($result->encrypted)->toBeTrue()
            ->and($result->encryption_driver)->toBe('laravel')
            ->and($result->payload)->toHaveKey('_encrypted');
    });

    it('skips already encrypted messages', function () {
        config()->set('chat-engine.encryption.enabled', true);

        $message = Ritechoice23\ChatEngine\Models\Message::make([
            'payload' => ['_encrypted' => 'already-encrypted'],
            'encrypted' => true,
            'encryption_driver' => 'laravel',
        ]);

        $originalPayload = $message->payload;

        $pipe = new EncryptPayload;
        $result = $pipe->handle($message, fn ($m) => $m);

        expect($result->payload)->toBe($originalPayload);
    });
});

describe('Chat Facade Encryption', function () {
    it('provides encryption manager via facade', function () {
        $manager = Chat::encryption();

        expect($manager)->toBeInstanceOf(EncryptionManager::class);
    });
});
