<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Builders\MessageBuilder;
use Ritechoice23\ChatEngine\Builders\ThreadBuilder;
use Ritechoice23\ChatEngine\Encryption\EncryptionManager;
use Ritechoice23\ChatEngine\Enums\ParticipantRole;
use Ritechoice23\ChatEngine\Events\ParticipantAdded;
use Ritechoice23\ChatEngine\Events\ParticipantRemoved;
use Ritechoice23\ChatEngine\Events\TypingStarted;
use Ritechoice23\ChatEngine\Events\TypingStopped;
use Ritechoice23\ChatEngine\Models\Thread;
use Ritechoice23\ChatEngine\Models\ThreadParticipant;
use Ritechoice23\ChatEngine\Support\MessagePipeline;
use Ritechoice23\ChatEngine\Support\PolicyChecker;
use Ritechoice23\ChatEngine\Support\PresenceManager;
use Ritechoice23\ChatEngine\Support\RetentionManager;

class Chat
{
    /**
     * Create a new thread builder.
     */
    public function thread(): ThreadBuilder
    {
        return new ThreadBuilder;
    }

    /**
     * Create a new message builder.
     */
    public function message(): MessageBuilder
    {
        return new MessageBuilder;
    }

    /**
     * Get the presence manager.
     */
    public function presence(): PresenceManager
    {
        return new PresenceManager;
    }

    /**
     * Get the retention manager.
     */
    public function retention(): RetentionManager
    {
        return new RetentionManager;
    }

    /**
     * Get the pipeline manager.
     */
    public function pipeline(): MessagePipeline
    {
        return new MessagePipeline;
    }

    /**
     * Get the policy checker.
     */
    public function policy(): PolicyChecker
    {
        return new PolicyChecker;
    }

    /**
     * Get the encryption manager.
     */
    public function encryption(): EncryptionManager
    {
        return new EncryptionManager;
    }

    /**
     * Get threads for an actor.
     */
    public function threadsFor(Model $actor): \Illuminate\Database\Eloquent\Builder
    {
        $threadModel = config('chat-engine.models.thread', Models\Thread::class);

        return $threadModel::query()->withParticipant($actor);
    }

    /**
     * Get unread count for an actor.
     */
    public function unreadCountFor(Model $actor): int
    {
        if (method_exists($actor, 'getUnreadMessagesCount')) {
            return $actor->getUnreadMessagesCount();
        }

        return 0;
    }

    /**
     * Add a participant to a thread.
     */
    public function addParticipant(
        Thread $thread,
        Model $actor,
        ParticipantRole $role = ParticipantRole::MEMBER
    ): ThreadParticipant {
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
    public function removeParticipant(Thread $thread, Model $actor): bool
    {
        $participant = $thread->getParticipant($actor);

        if (! $participant) {
            return false;
        }

        $participant->leave();

        ParticipantRemoved::dispatch($thread, $actor, $participant);

        return true;
    }

    /**
     * Dispatch typing started event.
     */
    public function startTyping(Thread $thread, Model $actor): void
    {
        TypingStarted::dispatch($thread, $actor);
    }

    /**
     * Dispatch typing stopped event.
     */
    public function stopTyping(Thread $thread, Model $actor): void
    {
        TypingStopped::dispatch($thread, $actor);
    }

    /**
     * Mark all messages in a thread as read by an actor.
     */
    public function markThreadAsRead(Thread $thread, Model $actor): int
    {
        $messages = $thread->messages()
            ->whereDoesntHave('deliveries', function ($query) use ($actor) {
                $query->where('actor_type', $actor->getMorphClass())
                    ->where('actor_id', $actor->getKey())
                    ->whereNotNull('read_at');
            })
            ->where(function ($q) use ($actor) {
                $q->where('sender_type', '!=', $actor->getMorphClass())
                    ->orWhere('sender_id', '!=', $actor->getKey());
            })
            ->whereNull('deleted_at')
            ->get();

        foreach ($messages as $message) {
            $message->markAsReadBy($actor);
        }

        return $messages->count();
    }
}
