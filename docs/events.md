# Events

All chat actions dispatch domain events. Use these for real-time updates, notifications, analytics, and integration.

## Event Architecture

- All events extend `Ritechoice23\ChatEngine\Events\ChatEvent`
- Events are dispatched synchronously (use queued listeners for async work)
- Events contain the relevant models, ready for broadcasting

## Event Reference

### Thread Events

| Event | When Dispatched | Properties |
|-------|-----------------|------------|
| `ThreadCreated` | New thread created | `$thread` |
| `ParticipantAdded` | User joins/added to thread | `$thread`, `$actor`, `$participant`, `$role` |
| `ParticipantRemoved` | User leaves/removed from thread | `$thread`, `$actor`, `$participant` |

### Message Events

| Event | When Dispatched | Properties |
|-------|-----------------|------------|
| `MessageSent` | New message sent | `$message`, `$thread`, `$sender` |
| `MessageEdited` | Message edited | `$message`, `$version`, `$editor` |
| `MessageDelivered` | Message reaches client | `$message`, `$actor`, `$delivery` |
| `MessageRead` | Recipient reads message | `$message`, `$actor`, `$delivery` |
| `MessageDeletedForActor` | Deleted for one user | `$message`, `$actor`, `$deletion` |
| `MessageDeletedGlobally` | Deleted for everyone | `$message`, `$actor` |
| `MessageRestored` | Deleted message restored | `$message`, `$actor` |

### Presence Events

| Event | When Dispatched | Properties |
|-------|-----------------|------------|
| `TypingStarted` | User starts typing | `$thread`, `$actor` |
| `TypingStopped` | User stops typing | `$thread`, `$actor` |

## Registering Listeners (Laravel 12)

Register in `AppServiceProvider::boot()`:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Event;
use Ritechoice23\ChatEngine\Events\MessageSent;
use App\Listeners\SendMessageNotification;
use App\Listeners\BroadcastMessage;

public function boot(): void
{
    Event::listen(MessageSent::class, SendMessageNotification::class);
    Event::listen(MessageSent::class, BroadcastMessage::class);
}
```

### Closure Listeners

For simple handlers:

```php
Event::listen(function (MessageSent $event) {
    Log::info('Message sent', [
        'thread' => $event->thread->id,
        'sender' => $event->sender->id,
    ]);
});
```

## Listener Examples

### Push Notification

```php
// app/Listeners/SendMessageNotification.php
namespace App\Listeners;

use Ritechoice23\ChatEngine\Events\MessageSent;

class SendMessageNotification
{
    public function handle(MessageSent $event): void
    {
        // Notify all participants except sender
        $recipients = $event->thread->activeParticipants()
            ->where('actor_id', '!=', $event->sender->id)
            ->get();
        
        foreach ($recipients as $participant) {
            $participant->actor->notify(
                new NewMessageNotification($event->message)
            );
        }
    }
}
```

### Real-Time Broadcasting

```php
// app/Listeners/BroadcastMessage.php
namespace App\Listeners;

use Ritechoice23\ChatEngine\Events\MessageSent;
use App\Events\ChatMessageBroadcast;

class BroadcastMessage
{
    public function handle(MessageSent $event): void
    {
        broadcast(new ChatMessageBroadcast($event->message))
            ->toOthers();
    }
}
```

### Analytics

```php
Event::listen(MessageSent::class, function (MessageSent $event) {
    Analytics::track('message_sent', [
        'thread_type' => $event->thread->type,
        'message_type' => $event->message->type,
        'has_mentions' => !empty($event->message->payload['mentions'] ?? []),
    ]);
});
```

## Queued Listeners

For slow operations, implement `ShouldQueue`:

```php
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessMessageMentions implements ShouldQueue
{
    public $queue = 'chat';  // Specific queue
    
    public function handle(MessageSent $event): void
    {
        // Process @mentions, send notifications
        $mentions = $event->message->payload['mentions'] ?? [];
        foreach ($mentions as $mention) {
            // Heavy processing...
        }
    }
}
```

## Event Subscribers

Group related listeners in a subscriber class:

```php
// app/Listeners/ChatEventSubscriber.php
namespace App\Listeners;

use Illuminate\Events\Dispatcher;
use Ritechoice23\ChatEngine\Events\MessageSent;
use Ritechoice23\ChatEngine\Events\MessageRead;
use Ritechoice23\ChatEngine\Events\ThreadCreated;

class ChatEventSubscriber
{
    public function handleMessageSent(MessageSent $event): void
    {
        // ...
    }
    
    public function handleMessageRead(MessageRead $event): void
    {
        // Update unread badge count
    }
    
    public function handleThreadCreated(ThreadCreated $event): void
    {
        // Log new conversation
    }
    
    public function subscribe(Dispatcher $events): array
    {
        return [
            MessageSent::class => 'handleMessageSent',
            MessageRead::class => 'handleMessageRead',
            ThreadCreated::class => 'handleThreadCreated',
        ];
    }
}
```

Register subscriber:

```php
// AppServiceProvider::boot()
Event::subscribe(ChatEventSubscriber::class);
```

## Manual Dispatch

Trigger events programmatically:

```php
use Ritechoice23\ChatEngine\Events\TypingStarted;

// From a controller or WebSocket handler
TypingStarted::dispatch($thread, $user);
```

## Testing Events

```php
use Illuminate\Support\Facades\Event;
use Ritechoice23\ChatEngine\Events\MessageSent;

it('dispatches event when message sent', function () {
    Event::fake([MessageSent::class]);
    
    Chat::message()->from($user)->to($thread)->text('Hello')->send();
    
    Event::assertDispatched(MessageSent::class, function ($event) use ($thread) {
        return $event->thread->id === $thread->id;
    });
});
```
