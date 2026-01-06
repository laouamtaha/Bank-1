<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Support;

use Illuminate\Support\Collection;
use Ritechoice23\ChatEngine\Enums\ThreadType;

class ThreadHasher
{
    /**
     * Generate a unique hash for thread participants.
     *
     * @param  Collection<array{actor: \Illuminate\Database\Eloquent\Model, role: \Ritechoice23\ChatEngine\Enums\ParticipantRole}>  $participants
     */
    public static function generate(
        Collection $participants,
        bool $includeRoles = true,
        ?ThreadType $type = null
    ): string {
        $parts = $participants
            ->map(function (array $participant) use ($includeRoles) {
                $actor = $participant['actor'];
                $key = $actor->getMorphClass().':'.$actor->getKey();

                if ($includeRoles) {
                    $key .= ':'.$participant['role']->value;
                }

                return $key;
            })
            ->sort()
            ->values()
            ->all();

        $hashInput = implode('|', $parts);

        if ($type !== null) {
            $hashInput = $type->value.'||'.$hashInput;
        }

        return hash('sha256', $hashInput);
    }

    /**
     * Generate hash for two actors (direct message).
     */
    public static function forDirectMessage(
        \Illuminate\Database\Eloquent\Model $actorA,
        \Illuminate\Database\Eloquent\Model $actorB
    ): string {
        $parts = collect([
            $actorA->getMorphClass().':'.$actorA->getKey(),
            $actorB->getMorphClass().':'.$actorB->getKey(),
        ])->sort()->values()->all();

        $hashInput = ThreadType::DIRECT->value.'||'.implode('|', $parts);

        return hash('sha256', $hashInput);
    }
}
