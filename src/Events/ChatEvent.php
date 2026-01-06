<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class ChatEvent
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly string $occurredAt;

    public function __construct()
    {
        $this->occurredAt = now()->toIso8601String();
    }
}
