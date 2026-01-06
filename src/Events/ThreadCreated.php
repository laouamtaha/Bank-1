<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Events;

use Ritechoice23\ChatEngine\Models\Thread;

class ThreadCreated extends ChatEvent
{
    public function __construct(
        public readonly Thread $thread,
    ) {
        parent::__construct();
    }
}
