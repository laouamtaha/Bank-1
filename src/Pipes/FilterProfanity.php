<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Pipes;

use Ritechoice23\ChatEngine\Contracts\MessagePipe;
use Ritechoice23\ChatEngine\Models\Message;

class FilterProfanity implements MessagePipe
{
    /**
     * Profanity word list.
     */
    protected array $profanityList = [];

    /**
     * Replacement character/string.
     */
    protected string $replacement = '*';

    /**
     * Filter mode: 'asterisk', 'remove', 'reject'
     */
    protected string $mode = 'asterisk';

    /**
     * Create a new filter instance.
     *
     * @param  array<string>|null  $profanityList
     */
    public function __construct(?array $profanityList = null, ?string $replacement = null, ?string $mode = null)
    {
        $this->profanityList = $profanityList ?? config('chat-engine.profanity.words', []);
        $this->replacement = $replacement ?? config('chat-engine.profanity.replacement', '*');
        $this->mode = $mode ?? config('chat-engine.profanity.mode', 'asterisk');
    }

    /**
     * Filter profanity from message content.
     */
    public function handle(Message $message, \Closure $next): Message
    {
        if (empty($this->profanityList)) {
            return $next($message);
        }

        $payload = $message->payload;

        // Filter text content
        if (isset($payload['content'])) {
            $payload['content'] = $this->filter($payload['content']);
        }

        // Filter caption
        if (isset($payload['caption'])) {
            $payload['caption'] = $this->filter($payload['caption']);
        }

        $message->payload = $payload;

        return $next($message);
    }

    /**
     * Filter profanity from text.
     *
     * @throws \InvalidArgumentException
     */
    protected function filter(string $text): string
    {
        if (empty($this->profanityList)) {
            return $text;
        }

        $pattern = '/\b('.implode('|', array_map('preg_quote', $this->profanityList)).')\b/i';

        return match ($this->mode) {
            'asterisk' => preg_replace_callback(
                $pattern,
                fn ($matches) => str_repeat($this->replacement, strlen($matches[0])),
                $text
            ),
            'remove' => preg_replace($pattern, '', $text),
            'reject' => $this->containsProfanity($text, $pattern)
            ? throw new \InvalidArgumentException('Message contains inappropriate content.')
            : $text,
            default => $text,
        };
    }

    /**
     * Check if text contains profanity.
     */
    protected function containsProfanity(string $text, string $pattern): bool
    {
        return preg_match($pattern, $text) === 1;
    }

    /**
     * Set profanity list on the fly.
     *
     * @param  array<string>  $words
     */
    public function setProfanityList(array $words): self
    {
        $this->profanityList = $words;

        return $this;
    }

    /**
     * Add words to the profanity list.
     *
     * @param  array<string>  $words
     */
    public function addWords(array $words): self
    {
        $this->profanityList = array_merge($this->profanityList, $words);

        return $this;
    }

    /**
     * Set the replacement character/string.
     */
    public function setReplacement(string $replacement): self
    {
        $this->replacement = $replacement;

        return $this;
    }

    /**
     * Set the filter mode.
     */
    public function setMode(string $mode): self
    {
        $this->mode = $mode;

        return $this;
    }
}
