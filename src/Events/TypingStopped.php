<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Events;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Models\Thread;

class TypingStopped extends ChatEvent
{
    public function __construct(
        public readonly Thread $thread,
        public readonly Model $actor,
    ) {
        parent::__construct();
    }
}
