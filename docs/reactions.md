# Reactions

Message reactions powered by `ritechoice23/laravel-reactions`. Supports any text or emoji as reaction type.

## How It Works

- Reactions are polymorphic: any actor can react to any message
- One reaction per actor per message (changing replaces previous)
- Supports text (`'like'`, `'love'`) or emoji (`'🔥'`, `'❤️'`)

## Adding Reactions

```php
// React to a message
$user->react($message, 'like');
$user->react($message, '🔥');

// Change reaction (auto-replaces previous)
$user->react($message, 'love');  // Now 'love' instead of 'like'

// Remove reaction
$user->unreact($message);
```

## Checking User's Reaction

```php
// Has user reacted at all?
$user->hasReactedTo($message);  // bool

// Get user's reaction type
$reaction = $user->reactionTo($message);  // 'love' or null
```

## Message Reaction Data

```php
// Total reaction count
$message->reactionsCount();  // 15

// Breakdown by type
$message->reactionsBreakdown();
// ['like' => 8, 'love' => 5, '🔥' => 2]

// Check if specific user reacted
$message->isReactedBy($user);  // bool

// Get user's reaction record
$reaction = $message->reactionBy($user);
$reaction->reaction_type;  // 'love'
$reaction->created_at;     // When they reacted
```

## Querying Reactions

```php
use Ritechoice23\Reactions\Models\Reaction;

// All reactions of a type
$loves = Reaction::byType('love')->get();

// All reactions by a user
$userReactions = Reaction::byReactor($user)->get();

// All reactions on a message
$messageReactions = Reaction::byReactable($message)->get();

// Combine filters
$userLoves = Reaction::byReactor($user)
    ->byType('love')
    ->get();
```

## In API Resources

When using `MessageResource`, reactions are included automatically:

```php
MessageResource::viewAs($currentUser);
$array = (new MessageResource($message))->resolve(request());
```

Response:

```json
{
  "reactions": {
    "count": 15,
    "breakdown": {"like": 8, "love": 5, "🔥": 2},
    "has_reacted": true,
    "user_reaction": "love"
  }
}
```

- `has_reacted` and `user_reaction` require `viewAs()` to be set

## Common Reaction Sets

### Emoji Style

```javascript
const reactions = ['👍', '❤️', '😂', '😮', '😢', '😡'];
```

### Text Style (Slack-like)

```javascript
const reactions = ['like', 'love', 'celebrate', 'insightful', 'curious'];
```

### Custom

Store any string - the package doesn't restrict types:

```php
$user->react($message, 'custom_reaction_type');
```

## Performance Tips

### Eager Load Reaction Counts

```php
// With reaction counts for list views
$messages = Message::withReactionsCount()->get();

foreach ($messages as $message) {
    echo $message->reactions_count;
}
```

### Check Reaction Status in Bulk

```php
// For current user across many messages
$messages = Message::withReactionStatus($user)->get();

foreach ($messages as $message) {
    if ($message->has_reacted) {
        echo "You reacted with: " . $message->reactor_reaction_type;
    }
}
```

## Events

The reactions package dispatches its own events. Listen in `AppServiceProvider`:

```php
use Ritechoice23\Reactions\Events\Reacted;
use Ritechoice23\Reactions\Events\Unreacted;

Event::listen(Reacted::class, function ($event) {
    // Send notification to message author
    if ($event->reactable instanceof Message) {
        $event->reactable->sender->notify(
            new MessageReactedNotification($event->reactor, $event->reaction)
        );
    }
});
```
