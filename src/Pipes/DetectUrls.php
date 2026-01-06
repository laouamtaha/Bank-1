<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Pipes;

use Ritechoice23\ChatEngine\Contracts\MessagePipe;
use Ritechoice23\ChatEngine\Models\Message;

class DetectUrls implements MessagePipe
{
    /**
     * Detect URLs in the message content.
     */
    public function handle(Message $message, \Closure $next): Message
    {
        $payload = $message->payload;

        // Only process text-based messages
        if (! isset($payload['content'])) {
            return $next($message);
        }

        $content = $payload['content'];
        $urls = [];

        // Regex pattern for URLs
        $pattern = '/https?:\/\/[^\s<>"{}|\\^`\[\]]+/i';
        preg_match_all($pattern, $content, $matches);

        if (! empty($matches[0])) {
            foreach ($matches[0] as $url) {
                $urls[] = [
                    'url' => $url,
                    'domain' => parse_url($url, PHP_URL_HOST),
                ];
            }
        }

        if (! empty($urls)) {
            $payload['urls'] = $urls;
            $message->payload = $payload;
        }

        return $next($message);
    }
}
