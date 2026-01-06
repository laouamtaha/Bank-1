<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Policies;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Models\Message;

class MessagePolicy
{
    /**
     * Determine if the actor can view the message.
     */
    public function view(Model $actor, Message $message): bool
    {
        // Can't view globally deleted messages
        if ($message->deleted_at !== null) {
            return false;
        }

        // Can't view if deleted for this specific actor
        if ($message->isDeletedFor($actor)) {
            return false;
        }

        // Must be a participant in the thread
        return $message->thread->hasParticipant($actor);
    }

    /**
     * Determine if the actor can edit the message.
     */
    public function edit(Model $actor, Message $message): bool
    {
        // Can't edit deleted messages
        if ($message->deleted_at !== null) {
            return false;
        }

        // Only the sender can edit their own messages
        return $message->isSentBy($actor);
    }

    /**
     * Determine if the actor can delete the message.
     */
    public function delete(Model $actor, Message $message): bool
    {
        // Can delete your own messages
        if ($message->isSentBy($actor)) {
            return true;
        }

        // Admins and owners can delete any message
        $participant = $message->thread->getParticipant($actor);

        return $participant && $participant->canManageParticipants();
    }

    /**
     * Determine if the actor can delete the message for themselves only.
     */
    public function deleteForSelf(Model $actor, Message $message): bool
    {
        // Must be a participant in the thread
        return $message->thread->hasParticipant($actor);
    }

    /**
     * Determine if the actor can react to the message.
     */
    public function react(Model $actor, Message $message): bool
    {
        // Can't react to deleted messages
        if ($message->deleted_at !== null || $message->isDeletedFor($actor)) {
            return false;
        }

        // Must be a participant in the thread
        return $message->thread->hasParticipant($actor);
    }
}
