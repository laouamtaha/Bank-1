<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Pipes;

use Closure;
use Ritechoice23\ChatEngine\Contracts\MessagePipe;
use Ritechoice23\ChatEngine\Encryption\EncryptionManager;
use Ritechoice23\ChatEngine\Models\Message;

/**
 * Encrypts message payload before storage.
 *
 * This pipe should run AFTER content processing (sanitization, mentions, etc.)
 * but BEFORE the message is persisted.
 */
class EncryptPayload implements MessagePipe
{
    protected EncryptionManager $encryption;

    public function __construct(?EncryptionManager $encryption = null)
    {
        $this->encryption = $encryption ?? new EncryptionManager;
    }

    public function handle(Message $message, Closure $next): Message
    {
        // Only encrypt if enabled
        if (! $this->encryption->isEnabled()) {
            return $next($message);
        }

        // Skip if already encrypted
        if ($message->encrypted) {
            return $next($message);
        }

        // Build context for encryption (useful for E2E with participant keys)
        $context = [
            'thread_id' => $message->thread_id,
            'sender_type' => $message->sender_type,
            'sender_id' => $message->sender_id,
        ];

        // Encrypt payload
        $encryptedPayload = $this->encryption->encrypt($message->payload, $context);

        // Store encrypted payload as string in a special key
        $message->payload = ['_encrypted' => $encryptedPayload];
        $message->encrypted = true;
        $message->encryption_driver = $this->encryption->getDriverName();

        return $next($message);
    }
}
