<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Support;

use Illuminate\Pipeline\Pipeline;
use Ritechoice23\ChatEngine\Models\Message;

class MessagePipeline
{
    /**
     * Process a message through the configured pipeline.
     *
     * @param  array<string>  $pipes
     */
    public function process(Message $message, array $pipes = []): Message
    {
        if (empty($pipes)) {
            $pipes = $this->getDefaultPipes();
        }

        return app(Pipeline::class)
            ->send($message)
            ->through($pipes)
            ->thenReturn();
    }

    /**
     * Get the default pipeline pipes from config.
     *
     * @return array<string>
     */
    protected function getDefaultPipes(): array
    {
        return config('chat-engine.pipelines.message', []);
    }
}
