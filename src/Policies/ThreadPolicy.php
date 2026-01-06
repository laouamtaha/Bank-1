<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Policies;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Models\Thread;

class ThreadPolicy
{
    /**
     * Determine if the actor can view the thread.
     */
    public function view(Model $actor, Thread $thread): bool
    {
        return $thread->hasParticipant($actor);
    }

    /**
     * Determine if the actor can send messages in the thread.
     */
    public function sendMessage(Model $actor, Thread $thread): bool
    {
        $participant = $thread->getParticipant($actor);

        return $participant && $participant->isActive();
    }

    /**
     * Determine if the actor can add participants to the thread.
     */
    public function addParticipant(Model $actor, Thread $thread): bool
    {
        $participant = $thread->getParticipant($actor);

        if (! $participant || ! $participant->isActive()) {
            return false;
        }

        return $participant->canManageParticipants();
    }

    /**
     * Determine if the actor can remove participants from the thread.
     */
    public function removeParticipant(Model $actor, Thread $thread): bool
    {
        $participant = $thread->getParticipant($actor);

        if (! $participant || ! $participant->isActive()) {
            return false;
        }

        return $participant->canManageParticipants();
    }

    /**
     * Determine if the actor can update the thread settings.
     */
    public function update(Model $actor, Thread $thread): bool
    {
        $participant = $thread->getParticipant($actor);

        if (! $participant || ! $participant->isActive()) {
            return false;
        }

        return $participant->canManageParticipants();
    }

    /**
     * Determine if the actor can delete the thread.
     */
    public function delete(Model $actor, Thread $thread): bool
    {
        $participant = $thread->getParticipant($actor);

        if (! $participant || ! $participant->isActive()) {
            return false;
        }

        return $participant->canDeleteThread();
    }

    /**
     * Determine if the actor can leave the thread.
     */
    public function leave(Model $actor, Thread $thread): bool
    {
        $participant = $thread->getParticipant($actor);

        return $participant && $participant->isActive();
    }
}
