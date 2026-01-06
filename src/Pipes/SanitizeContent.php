<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Pipes;

use Ritechoice23\ChatEngine\Contracts\MessagePipe;
use Ritechoice23\ChatEngine\Models\Message;

class SanitizeContent implements MessagePipe
{
    /**
     * Sanitize message content to prevent XSS.
     */
    public function handle(Message $message, \Closure $next): Message
    {
        $payload = $message->payload;

        // Sanitize text content
        if (isset($payload['content'])) {
            $payload['content'] = $this->sanitize($payload['content']);
        }

        // Sanitize caption for media
        if (isset($payload['caption'])) {
            $payload['caption'] = $this->sanitize($payload['caption']);
        }

        $message->payload = $payload;

        return $next($message);
    }

    /**
     * Sanitize a string.
     */
    protected function sanitize(string $content): string
    {
        // Remove potentially dangerous HTML tags
        $content = strip_tags($content, '<b><i><u><s><em><strong><code><pre><a>');

        // Encode special characters
        $content = htmlspecialchars($content, ENT_QUOTES | ENT_HTML5, 'UTF-8', false);

        return $content;
    }
}
