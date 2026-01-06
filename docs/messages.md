# Messages

Messages are polymorphic - any actor can send to any thread they participate in.

## Message Types

The `type` field identifies the payload structure for frontend rendering:

| Type | Required Fields | Optional Fields |
|------|-----------------|-----------------|
| `text` | `content` | `mentions`, `urls` |
| `image` | `url` | `caption`, `width`, `height` |
| `video` | `url` | `thumbnail`, `duration` |
| `audio` | `url` | `duration` |
| `file` | `url`, `filename` | `mimeType`, `size` |
| `location` | `latitude`, `longitude` | `address` |
| `contact` | `name` | `phone`, `email` |
| `system` | `content` | - |
| `custom` | (your structure) | - |

**Note**: Payload is stored as JSON. The package validates structure when using builder methods.

## Sending Messages

### Text Message

```php
$message = Chat::message()
    ->from($user)
    ->to($thread)
    ->text('Hello world!')
    ->send();
```

### Image

```php
Chat::message()
    ->from($user)
    ->to($thread)
    ->image('https://example.com/photo.jpg', 'Nice sunset')
    ->send();
```

### Video

```php
Chat::message()
    ->from($user)
    ->to($thread)
    ->video(
        url: 'https://example.com/video.mp4',
        thumbnail: 'https://example.com/thumb.jpg',
        duration: 120  // seconds
    )
    ->send();
```

### File/Document

```php
Chat::message()
    ->from($user)
    ->to($thread)
    ->file(
        url: 'https://example.com/doc.pdf',
        filename: 'report.pdf',
        mimeType: 'application/pdf',
        size: 1024000  // bytes
    )
    ->send();
```

### Location

```php
Chat::message()
    ->from($user)
    ->to($thread)
    ->location(40.7128, -74.0060, 'New York, NY')
    ->send();
```

### Custom Payload

For app-specific message types (polls, cards, embeds):

```php
Chat::message()
    ->from($user)
    ->to($thread)
    ->type(MessageType::CUSTOM)
    ->payload([
        'poll' => [
            'question' => 'Lunch?',
            'options' => ['Pizza', 'Sushi', 'Salad'],
            'votes' => []
        ]
    ])
    ->send();
```

### On Behalf Of

When one actor sends for another (bots, scheduled messages, impersonation):

```php
Chat::message()
    ->from($bot)           // sender = bot (who actually sent)
    ->onBehalfOf($user)    // author = user (on whose behalf)
    ->to($thread)
    ->text('Scheduled reminder!')
    ->send();
```

Both `sender` and `author` are stored. UI can show "Bot on behalf of User".

## Editing Messages

Edit behavior depends on `config('chat-engine.messages.immutable')`:

### Immutable Mode (default: `true`)

Edits create version records. Original payload preserved:

```php
$message = Chat::message()->from($user)->to($thread)->text('Hello')->send();

// Edit creates a new version
$version = $message->edit(['content' => 'Hello, World!'], $user);

// Check edit state
$message->isEdited();         // true
$message->versions()->count(); // 1

// Get current content
$message->currentPayload();   // ['content' => 'Hello, World!']

// Access version history
foreach ($message->versions as $version) {
    $version->payload;     // Previous content
    $version->editedBy;    // Who edited
    $version->created_at;  // When edited
}
```

### Mutable Mode (`immutable: false`)

Payload updated directly, no history:

```php
$message->edit(['content' => 'Updated'], $user);
$message->isEdited(); // false (no versions to check)
```

### Edit Time Limit

If configured, edits fail after the limit:

```php
// config: 'allow_edit_time_limit' => 15

$message = Chat::message()->from($user)->to($thread)->text('Typo')->send();

// After 16 minutes...
$message->edit(['content' => 'Fixed'], $user);
// Throws exception: "Edit time limit exceeded"
```

## Deleting Messages

Behavior depends on `config('chat-engine.messages.deletion_mode')`:

### Soft Delete (default)

```php
// Delete for everyone (sets deleted_at)
$message->deleteGlobally($actor);
$message->deleted_at;  // Timestamp
$message->isDeleted(); // true

// Delete for self only (adds to message_deletions table)
$message->deleteFor($actor);

// Restore globally deleted message
$message->restore($actor);
```

### Hard Delete

```php
$message->forceDelete(); // Permanent removal
```

### Visibility

Messages respect deletion state:

```php
// Get messages visible to actor
$messages = Message::visibleTo($actor)->get();
// Excludes: globally deleted, deleted for this actor
```

## Message Events

Actions dispatch events for integration:

| Action | Event |
|--------|-------|
| `send()` | `MessageSent` |
| `edit()` | `MessageEdited` |
| `deleteGlobally()` | `MessageDeletedGlobally` |
| `deleteFor()` | `MessageDeletedForActor` |
| `restore()` | `MessageRestored` |
| `markAsDeliveredTo()` | `MessageDelivered` |
| `markAsReadBy()` | `MessageRead` |

## Querying Messages

```php
// Messages in thread (paginated)
$messages = $thread->messages()
    ->with(['sender', 'author'])
    ->latest()
    ->paginate(50);

// Unread messages for actor
$unread = $thread->messages()
    ->whereDoesntHave('deliveries', function ($q) use ($user) {
        $q->where('actor_id', $user->id)->whereNotNull('read_at');
    })
    ->get();

// Messages sent by actor
$messages = $user->sentMessages()->latest()->get();
```
