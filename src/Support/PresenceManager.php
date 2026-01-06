<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Support;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Events\TypingStarted;
use Ritechoice23\ChatEngine\Events\TypingStopped;
use Ritechoice23\ChatEngine\Models\Thread;

class PresenceManager
{
    /**
     * Indicate that an actor has started typing in a thread.
     */
    public function typing(Model $actor, Thread $thread): void
    {
        TypingStarted::dispatch($thread, $actor);
    }

    /**
     * Indicate that an actor has stopped typing in a thread.
     */
    public function stopTyping(Model $actor, Thread $thread): void
    {
        TypingStopped::dispatch($thread, $actor);
    }

    /**
     * Indicate that an actor is online.
     * Dispatches a generic presence event - applications should listen and handle transport.
     */
    public function online(Model $actor): void
    {
        event('chat.presence.online', ['actor' => $actor]);
    }

    /**
     * Indicate that an actor is offline.
     * Dispatches a generic presence event - applications should listen and handle transport.
     */
    public function offline(Model $actor): void
    {
        event('chat.presence.offline', ['actor' => $actor]);
    }

    /**
     * Indicate that an actor is away.
     */
    public function away(Model $actor): void
    {
        event('chat.presence.away', ['actor' => $actor]);
    }

    /**
     * Update actor's last seen timestamp.
     * Applications should persist this if needed.
     */
    public function updateLastSeen(Model $actor): void
    {
        event('chat.presence.last_seen', [
            'actor' => $actor,
            'timestamp' => now(),
        ]);
    }
}
