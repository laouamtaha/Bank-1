<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Actions;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Enums\DeletionMode;
use Ritechoice23\ChatEngine\Events\MessageDeletedForActor;
use Ritechoice23\ChatEngine\Events\MessageDeletedGlobally;
use Ritechoice23\ChatEngine\Events\MessageRestored;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Models\MessageDeletion;
use Ritechoice23\ChatEngine\Policies\MessagePolicy;

class DeleteMessage
{
    /**
     * Delete a message for a specific actor only.
     */
    public function forActor(Message $message, Model $actor): MessageDeletion
    {
        // Check policy authorization
        $policy = new MessagePolicy;
        if (! $policy->deleteForSelf($actor, $message)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Not authorized to delete this message for yourself.'
            );
        }

        $deletion = $message->deletions()->firstOrCreate([
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
        ], [
            'deleted_at' => now(),
        ]);

        MessageDeletedForActor::dispatch($message, $actor, $deletion);

        return $deletion;
    }

    /**
     * Delete a message for everyone (global soft delete).
     */
    public function globally(Message $message, Model $deletedBy): bool
    {
        // Check policy authorization
        $policy = new MessagePolicy;
        if (! $policy->delete($deletedBy, $message)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Not authorized to delete this message globally.'
            );
        }

        $mode = DeletionMode::from(config('chat-engine.messages.deletion_mode', 'soft'));

        if ($mode === DeletionMode::HARD) {
            return $this->hardDelete($message, $deletedBy);
        }

        $message->deleted_at = now();
        $message->deleted_by_type = $deletedBy->getMorphClass();
        $message->deleted_by_id = $deletedBy->getKey();

        $result = $message->save();

        if ($result) {
            MessageDeletedGlobally::dispatch($message, $deletedBy);
        }

        return $result;
    }

    /**
     * Hard delete a message from the database.
     */
    public function hardDelete(Message $message, Model $deletedBy): bool
    {
        $mode = DeletionMode::from(config('chat-engine.messages.deletion_mode', 'soft'));

        if (! $mode->allowsHardDelete()) {
            throw new \InvalidArgumentException(
                'Hard delete is not allowed in the current deletion mode.'
            );
        }

        MessageDeletedGlobally::dispatch($message, $deletedBy);

        return $message->delete();
    }

    /**
     * Restore a message for a specific actor.
     */
    public function restoreForActor(Message $message, Model $actor): bool
    {
        $deleted = $message->deletions()
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey())
            ->delete() > 0;

        if ($deleted) {
            MessageRestored::dispatch($message, $actor);
        }

        return $deleted;
    }

    /**
     * Restore a globally soft-deleted message.
     */
    public function restoreGlobally(Message $message): bool
    {
        if ($message->deleted_at === null) {
            return false;
        }

        $message->deleted_at = null;
        $message->deleted_by_type = null;
        $message->deleted_by_id = null;

        return $message->save();
    }
}
