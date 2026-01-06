<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Contracts;

use Ritechoice23\ChatEngine\Models\Message;

interface MessagePipe
{
    /**
     * Process the message through this pipe.
     */
    public function handle(Message $message, \Closure $next): Message;
}
