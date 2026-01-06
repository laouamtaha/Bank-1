<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Traits;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Models\MessageDelivery;
use Ritechoice23\ChatEngine\Models\Thread;
use Ritechoice23\ChatEngine\Models\ThreadParticipant;
use Ritechoice23\Reactions\Traits\CanReact;
use Ritechoice23\Saveable\Traits\HasSaves;

/**
 * Trait for models that can participate in chat conversations.
 *
 * Models using this trait can:
 * - Participate in threads (send/receive messages)
 * - React to messages (via CanReact trait from laravel-reactions)
 * - Save/bookmark messages (via HasSaves trait from laravel-saveable)
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait CanChat
{
    use CanReact;
    use HasSaves;

    /**
     * Get all threads this actor participates in.
     */
    public function threads(): MorphToMany
    {
        return $this->morphToMany(
            config('chat-engine.models.thread', Thread::class),
            'actor',
            config('chat-engine.tables.thread_participants', 'thread_participants'),
            null,
            'thread_id'
        )->withPivot(['role', 'joined_at', 'left_at']);
    }

    /**
     * Get all active threads (not left).
     */
    public function activeThreads(): MorphToMany
    {
        return $this->threads()->wherePivotNull('left_at');
    }

    /**
     * Get thread participations.
     */
    public function threadParticipations(): MorphMany
    {
        return $this->morphMany(
            config('chat-engine.models.thread_participant', ThreadParticipant::class),
            'actor'
        );
    }

    /**
     * Get all messages sent by this actor.
     */
    public function sentMessages(): MorphMany
    {
        return $this->morphMany(
            config('chat-engine.models.message', Message::class),
            'sender'
        );
    }

    /**
     * Get all message deliveries for this actor.
     */
    public function messageDeliveries(): MorphMany
    {
        return $this->morphMany(
            config('chat-engine.models.message_delivery', MessageDelivery::class),
            'actor'
        );
    }

    /**
     * Check if this actor is a participant in a thread.
     */
    public function isParticipantIn(Thread|int $thread): bool
    {
        $threadId = $thread instanceof Thread ? $thread->id : $thread;

        return $this->threadParticipations()
            ->where('thread_id', $threadId)
            ->whereNull('left_at')
            ->exists();
    }

    /**
     * Get unread messages count across all threads.
     */
    public function getUnreadMessagesCount(): int
    {
        return Message::query()
            ->whereIn('thread_id', $this->activeThreads()->pluck('threads.id'))
            ->whereDoesntHave('deliveries', function ($query) {
                $query->where('actor_type', $this->getMorphClass())
                    ->where('actor_id', $this->getKey())
                    ->whereNotNull('read_at');
            })
            ->where(function ($query) {
                $query->where('sender_type', '!=', $this->getMorphClass())
                    ->orWhere('sender_id', '!=', $this->getKey());
            })
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * Get the direct thread with another actor.
     */
    public function getDirectThreadWith(self $actor): ?Thread
    {
        return $this->activeThreads()
            ->where('type', 'direct')
            ->whereHas('participants', function ($query) use ($actor) {
                $query->where('actor_type', $actor->getMorphClass())
                    ->where('actor_id', $actor->getKey())
                    ->whereNull('left_at');
            })
            ->first();
    }

    /**
     * Check if has a direct thread with another actor.
     */
    public function hasDirectThreadWith(self $actor): bool
    {
        return $this->getDirectThreadWith($actor) !== null;
    }
}
