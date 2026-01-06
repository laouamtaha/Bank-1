# Bookmarks

Save and organize important messages with bookmark functionality.

## Setup

The `CanChat` trait automatically includes the `HasSaves` trait from `laravel-saveable`, enabling any model using it to bookmark messages.

```php
use Ritechoice23\ChatEngine\Traits\CanChat;

class User extends Authenticatable
{
    use CanChat; // Includes HasSaves automatically
}
```

## Saving Messages

### Save a Message

```php
$user->saveItem($message);
```

### Unsave a Message

```php
$user->unsaveItem($message);
```

### Toggle Save

```php
$user->toggleSaveItem($message);
```

## Checking Status

### From User (Saver)

```php
if ($user->hasSavedItem($message)) {
    // User has this message bookmarked
}
```

### From Message (Saveable)

```php
if ($message->isSavedBy($user)) {
    // This message is saved by the user
}

// Count total saves
$saveCount = $message->timesSaved();
```

## Retrieving Saved Messages

```php
// Get all saved messages
$savedMessages = $user->savedItems(Message::class)->get();

// With ordering
$savedMessages = $user->savedItems(Message::class)
    ->orderByDesc('created_at')
    ->get();

// Paginated
$savedMessages = $user->savedItems(Message::class)->paginate(20);
```

## Collections

Organize bookmarks into custom collections.

### Create Collection

```php
$collection = $user->collections()->create(['name' => 'Important']);
$collection = $user->collections()->create(['name' => 'Work Related']);
```

### Save to Collection

```php
$user->saveItem($message, collection: $collection);
```

### Get Collection Items

```php
$messages = $collection->items();
```

### Get All Collections

```php
$collections = $user->collections()->get();
```

## Metadata

Attach notes or custom data to bookmarks.

```php
// Save with metadata
$user->saveItem($message, metadata: [
    'note' => 'Follow up on this',
    'priority' => 'high',
]);

// Access metadata via saved record
$savedRecord = $user->getSavedRecord($message);
$note = $savedRecord->metadata['note'];
```

## Ordering

Bookmarks are automatically ordered. The `auto_ordering` config in `saveable` determines if order is tracked automatically.

## Practical Examples

### User's Bookmarks Dashboard

```php
public function bookmarks(Request $request)
{
    $user = $request->user();

    return [
        'bookmarks' => $user->savedItems(Message::class)
            ->with(['thread', 'sender'])
            ->orderByDesc('created_at')
            ->paginate(20),
        'collections' => $user->collections()->withCount('saves')->get(),
    ];
}
```

### Toggle Bookmark Endpoint

```php
public function toggle(Request $request, Message $message)
{
    $user = $request->user();
    $user->toggleSaveItem($message);

    return [
        'saved' => $user->hasSavedItem($message),
        'count' => $message->timesSaved(),
    ];
}
```

### Messages with Bookmark Status

```php
$messages = $thread->messages()
    ->with('sender')
    ->get()
    ->map(fn($message) => [
        'id' => $message->id,
        'content' => $message->payload['content'],
        'is_bookmarked' => $message->isSavedBy($user),
    ]);
```
