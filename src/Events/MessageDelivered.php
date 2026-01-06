<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Events;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Models\MessageDelivery;

class MessageDelivered extends ChatEvent
{
    public function __construct(
        public readonly Message $message,
        public readonly Model $actor,
        public readonly MessageDelivery $delivery,
    ) {
        parent::__construct();
    }
}
