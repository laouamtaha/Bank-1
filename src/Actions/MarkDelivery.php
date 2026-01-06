<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Actions;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Events\MessageDelivered;
use Ritechoice23\ChatEngine\Events\MessageRead;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Models\MessageDelivery;
use Ritechoice23\ChatEngine\Models\Thread;

class MarkDelivery
{
    /**
     * Mark a message as delivered to an actor.
     */
    public function asDelivered(Message $message, Model $actor): MessageDelivery
    {
        if (! config('chat-engine.delivery.track_deliveries', true)) {
            throw new \RuntimeException('Delivery tracking is disabled.');
        }

        $delivery = $message->deliveries()->updateOrCreate([
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
        ], [
            'delivered_at' => now(),
        ]);

        MessageDelivered::dispatch($message, $actor, $delivery);

        return $delivery;
    }

    /**
     * Mark a message as read by an actor.
     */
    public function asRead(Message $message, Model $actor): MessageDelivery
    {
        if (! config('chat-engine.delivery.track_reads', true)) {
            throw new \RuntimeException('Read tracking is disabled.');
        }

        $existingDeliveredAt = $message->deliveries()
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey())
            ->value('delivered_at');

        $delivery = $message->deliveries()->updateOrCreate([
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
        ], [
            'delivered_at' => $existingDeliveredAt ?? now(),
            'read_at' => now(),
        ]);

        MessageRead::dispatch($message, $actor, $delivery);

        return $delivery;
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
            $this->asRead($message, $actor);
        }

        return $messages->count();
    }

    /**
     * Mark all messages in a thread as delivered to an actor.
     */
    public function markThreadAsDelivered(Thread $thread, Model $actor): int
    {
        $messages = $thread->messages()
            ->whereDoesntHave('deliveries', function ($query) use ($actor) {
                $query->where('actor_type', $actor->getMorphClass())
                    ->where('actor_id', $actor->getKey())
                    ->whereNotNull('delivered_at');
            })
            ->where(function ($q) use ($actor) {
                $q->where('sender_type', '!=', $actor->getMorphClass())
                    ->orWhere('sender_id', '!=', $actor->getKey());
            })
            ->whereNull('deleted_at')
            ->get();

        foreach ($messages as $message) {
            $this->asDelivered($message, $actor);
        }

        return $messages->count();
    }
}
