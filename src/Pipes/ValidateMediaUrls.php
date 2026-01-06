<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Pipes;

use Ritechoice23\ChatEngine\Contracts\MessagePipe;
use Ritechoice23\ChatEngine\Models\Message;

class ValidateMediaUrls implements MessagePipe
{
    /**
     * Validate that media URLs are accessible and properly formatted.
     */
    public function handle(Message $message, \Closure $next): Message
    {
        $payload = $message->payload;

        // Check for URL in different message types
        if (isset($payload['url'])) {
            $this->validateUrl($payload['url']);
        }

        // Validate thumbnail URLs
        if (isset($payload['thumbnail'])) {
            $this->validateUrl($payload['thumbnail']);
        }

        return $next($message);
    }

    /**
     * Validate a URL.
     *
     * @throws \InvalidArgumentException
     */
    protected function validateUrl(string $url): void
    {
        // Check if it's a valid URL format
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException("Invalid URL format: {$url}");
        }

        // Check if scheme is http or https
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new \InvalidArgumentException("URL must use http or https protocol: {$url}");
        }
    }
}
