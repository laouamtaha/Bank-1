<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Events;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Enums\ParticipantRole;
use Ritechoice23\ChatEngine\Models\Thread;
use Ritechoice23\ChatEngine\Models\ThreadParticipant;

class ParticipantAdded extends ChatEvent
{
    public function __construct(
        public readonly Thread $thread,
        public readonly Model $actor,
        public readonly ThreadParticipant $participant,
        public readonly ParticipantRole $role,
    ) {
        parent::__construct();
    }
}
