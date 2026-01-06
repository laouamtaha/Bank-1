<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Events;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Models\Message;

class MessageDeletedGlobally extends ChatEvent
{
    public function __construct(
        public readonly Message $message,
        public readonly Model $deletedBy,
    ) {
        parent::__construct();
    }
}
