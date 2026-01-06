<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Events;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Models\Thread;

class MessageSent extends ChatEvent
{
    public function __construct(
        public readonly Message $message,
        public readonly Thread $thread,
        public readonly Model $sender,
    ) {
        parent::__construct();
    }
}
