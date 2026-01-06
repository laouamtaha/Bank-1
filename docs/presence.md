# Presence

Presence indicators (typing, online/offline) are **event-driven only**. The package dispatches events - your app handles transport (WebSockets, SSE, polling).

## Why Event-Only?

- **Transport agnostic**: Works with Pusher, Ably, Laravel Reverb, or any system
- **No polling**: No database writes for ephemeral state
- **Scalable**: Presence state lives in your real-time infrastructure

## Typing Indicators

### Dispatch Events

```php
// User starts typing
Chat::startTyping($thread, $user);  // Dispatches TypingStarted

// User stops typing (timeout or sent message)
Chat::stopTyping($thread, $user);   // Dispatches TypingStopped
```

Or via PresenceManager:

```php
$presence = Chat::presence();

$presence->typing($user, $thread);
$presence->stopTyping($user, $thread);
```

### Frontend Integration

Typical flow:

```javascript
// 1. User types in input
input.addEventListener('keydown', () => {
    if (!isTyping) {
        socket.emit('typing:start', { threadId });
        isTyping = true;
    }
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        socket.emit('typing:stop', { threadId });
        isTyping = false;
    }, 2000);  // Stop after 2s of no typing
});

// 2. Listen for others typing
socket.on('user:typing', ({ userId, threadId }) => {
    showTypingIndicator(userId);
});
```

## Online Status

```php
$presence = Chat::presence();

$presence->online($user);      // Dispatches chat.presence.online
$presence->offline($user);     // Dispatches chat.presence.offline
$presence->away($user);        // Dispatches chat.presence.away
$presence->updateLastSeen($user);  // Dispatches chat.presence.last_seen
```

**Note**: These dispatch generic events, not the ChatEvent classes. Listen with:

```php
Event::listen('chat.presence.online', function ($payload) {
    $actor = $payload['actor'];
    // Broadcast to friends/contacts
});
```

## Events Reference

| Method | Event Dispatched | Payload |
|--------|------------------|---------|
| `typing()` | `TypingStarted` | `$thread`, `$actor` |
| `stopTyping()` | `TypingStopped` | `$thread`, `$actor` |
| `online()` | `chat.presence.online` | `['actor' => $actor]` |
| `offline()` | `chat.presence.offline` | `['actor' => $actor]` |
| `away()` | `chat.presence.away` | `['actor' => $actor]` |
| `updateLastSeen()` | `chat.presence.last_seen` | `['actor' => $actor, 'timestamp' => now()]` |

## Broadcasting Integration (Laravel 12)

### Register Listener

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Event;
use Ritechoice23\ChatEngine\Events\TypingStarted;
use App\Events\UserTyping;

public function boot(): void
{
    Event::listen(TypingStarted::class, function (TypingStarted $event) {
        broadcast(new UserTyping(
            thread: $event->thread,
            user: $event->actor
        ))->toOthers();  // Don't send back to sender
    });
}
```

### Broadcasting Event

```php
// app/Events/UserTyping.php
namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Ritechoice23\ChatEngine\Models\Thread;
use App\Models\User;

class UserTyping implements ShouldBroadcast
{
    public function __construct(
        public Thread $thread,
        public User $user
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("thread.{$this->thread->id}")];
    }
    
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
        ];
    }
}
```

## Persisting Last Seen

If you need persistent "last seen" status:

```php
// Listen and save
Event::listen('chat.presence.last_seen', function ($payload) {
    $payload['actor']->update(['last_seen_at' => $payload['timestamp']]);
});

// Trigger periodically from frontend
$presence->updateLastSeen($user);
```
