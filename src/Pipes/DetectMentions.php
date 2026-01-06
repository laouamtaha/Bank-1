<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Pipes;

use Ritechoice23\ChatEngine\Contracts\MessagePipe;
use Ritechoice23\ChatEngine\Models\Message;

class DetectMentions implements MessagePipe
{
    /**
     * Detect @mentions in the message payload.
     */
    public function handle(Message $message, \Closure $next): Message
    {
        $payload = $message->payload;

        // Only process text-based messages
        if (! isset($payload['content'])) {
            return $next($message);
        }

        $content = $payload['content'];
        $mentions = [];

        // Pattern: @username or @[Display Name](user_id)
        preg_match_all('/@\[([^\]]+)\]\((\d+)\)|@(\w+)/', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if (! empty($match[2])) {
                // Markdown-style mention: @[Name](id)
                $mentions[] = [
                    'name' => $match[1],
                    'id' => (int) $match[2],
                    'text' => $match[0],
                ];
            } elseif (! empty($match[3])) {
                // Simple mention: @username
                $mentions[] = [
                    'username' => $match[3],
                    'text' => $match[0],
                ];
            }
        }

        if (! empty($mentions)) {
            $payload['mentions'] = $mentions;
            $message->payload = $payload;
        }

        return $next($message);
    }
}
