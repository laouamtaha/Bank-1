# Delivery & Read Receipts

Track when messages are delivered to and read by recipients.

## Concepts

| Status | Meaning | When to Set |
|--------|---------|-------------|
| **Delivered** | Message reached client app | When client receives via WebSocket/push |
| **Read** | Recipient viewed the message | When thread is opened and message visible |

A message can be delivered but not read (notification received but not opened).

## Configuration

```php
// config/chat-engine.php
'delivery' => [
    'track_deliveries' => true,  // Enable delivered status
    'track_reads' => true,       // Enable read status
],
```

Disable if your app doesn't need these indicators to reduce database writes.

## Marking Delivery

### Via Action

```php
use Ritechoice23\ChatEngine\Actions\MarkDelivery;

$action = new MarkDelivery;

// When message arrives at client
$action->delivered($message, $actor);

// When user opens thread and views message
$action->read($message, $actor);
```

### On Message Model

```php
// Mark as delivered
$message->markAsDeliveredTo($actor);

// Mark as read (automatically marks delivered too)
$message->markAsReadBy($actor);
```

## Checking Status

```php
// For a specific actor
$message->isDeliveredTo($actor); // bool
$message->isReadBy($actor);      // bool

// Get delivery record with timestamps
$delivery = $message->deliveries()
    ->where('actor_type', $actor->getMorphClass())
    ->where('actor_id', $actor->getKey())
    ->first();

if ($delivery) {
    $delivery->delivered_at; // When delivered
    $delivery->read_at;      // When read (null if unread)
}
```

## Marking Thread as Read

Mark all messages in a thread as read at once:

```php
$count = Chat::markThreadAsRead($thread, $actor);
// Returns number of messages marked
```

This is more efficient than marking each message individually.

## Unread Count

```php
// Total unread across all threads
$count = $user->getUnreadMessagesCount();

// Or via facade
$count = Chat::unreadCountFor($user);
```

The count excludes:
- Messages sent by the actor themselves
- Deleted messages
- Messages in threads the actor has left

## Events

Events dispatch when delivery states change:

```php
// MessageDelivered
Event::listen(MessageDelivered::class, function ($event) {
    $event->message;   // The message
    $event->actor;     // Who received it
    $event->delivery;  // The delivery record
});

// MessageRead
Event::listen(MessageRead::class, function ($event) {
    // Same structure
});
```

## Implementation Example

Typical flow in a real-time app:

```php
// 1. User opens app → mark pending messages as delivered
public function onConnect(User $user)
{
    $pendingMessages = Message::whereDoesntHave('deliveries', function ($q) use ($user) {
            $q->where('actor_id', $user->id);
        })
        ->whereHas('thread.participants', function ($q) use ($user) {
            $q->where('actor_id', $user->id);
        })
        ->get();
    
    foreach ($pendingMessages as $message) {
        $message->markAsDeliveredTo($user);
    }
}

// 2. User opens thread → mark all as read
public function onOpenThread(Thread $thread, User $user)
{
    Chat::markThreadAsRead($thread, $user);
}
```
