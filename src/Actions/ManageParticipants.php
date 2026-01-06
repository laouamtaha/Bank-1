<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Actions;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Enums\ParticipantRole;
use Ritechoice23\ChatEngine\Events\ParticipantAdded;
use Ritechoice23\ChatEngine\Events\ParticipantRemoved;
use Ritechoice23\ChatEngine\Models\Thread;
use Ritechoice23\ChatEngine\Models\ThreadParticipant;
use Ritechoice23\ChatEngine\Policies\ThreadPolicy;

class ManageParticipants
{
    /**
     * Add a participant to a thread.
     */
    public function add(
        Thread $thread,
        Model $actor,
        ParticipantRole $role = ParticipantRole::MEMBER,
        ?Model $addedBy = null
    ): ThreadParticipant {
        // Check policy authorization (if someone is adding the participant)
        if ($addedBy && $addedBy->getKey() !== $actor->getKey()) {
            $policy = new ThreadPolicy;
            if (! $policy->addParticipant($addedBy, $thread)) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'Not authorized to add participants to this thread.'
                );
            }
        }

        // Check if already a participant
        $existing = $thread->getParticipant($actor);
        if ($existing && $existing->isActive()) {
            return $existing;
        }

        // If previously left, rejoin instead of creating new
        if ($existing) {
            $existing->rejoin();
            $existing->role = $role->value;
            $existing->save();

            ParticipantAdded::dispatch($thread, $actor, $existing, $role);

            return $existing;
        }

        $participant = $thread->participants()->create([
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
            'role' => $role->value,
        ]);

        ParticipantAdded::dispatch($thread, $actor, $participant, $role);

        return $participant;
    }

    /**
     * Remove a participant from a thread.
     */
    public function remove(Thread $thread, Model $actor, ?Model $removedBy = null): bool
    {
        // Check policy authorization (if someone is removing the participant)
        if ($removedBy && $removedBy->getKey() !== $actor->getKey()) {
            $policy = new ThreadPolicy;
            if (! $policy->removeParticipant($removedBy, $thread)) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'Not authorized to remove participants from this thread.'
                );
            }
        }

        $participant = $thread->getParticipant($actor);

        if (! $participant || ! $participant->isActive()) {
            return false;
        }

        $participant->leave();

        ParticipantRemoved::dispatch($thread, $actor, $participant);

        return true;
    }

    /**
     * Update a participant's role.
     */
    public function updateRole(
        Thread $thread,
        Model $actor,
        ParticipantRole $newRole
    ): ?ThreadParticipant {
        $participant = $thread->getParticipant($actor);

        if (! $participant || ! $participant->isActive()) {
            return null;
        }

        $participant->role = $newRole->value;
        $participant->save();

        return $participant;
    }

    /**
     * Transfer ownership to another participant.
     */
    public function transferOwnership(
        Thread $thread,
        Model $currentOwner,
        Model $newOwner
    ): bool {
        $currentParticipant = $thread->getParticipant($currentOwner);
        $newParticipant = $thread->getParticipant($newOwner);

        if (! $currentParticipant || ! $currentParticipant->hasRole(ParticipantRole::OWNER)) {
            throw new \InvalidArgumentException('Current owner is not the thread owner.');
        }

        if (! $newParticipant || ! $newParticipant->isActive()) {
            throw new \InvalidArgumentException('New owner must be an active participant.');
        }

        $currentParticipant->role = ParticipantRole::ADMIN->value;
        $newParticipant->role = ParticipantRole::OWNER->value;

        $currentParticipant->save();
        $newParticipant->save();

        return true;
    }
}
