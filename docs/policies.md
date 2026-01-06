# Policies & Authorization

Built-in policies control who can do what with threads and messages.

## How It Works

1. Actions check policies automatically before executing
2. Policies examine the actor's role in the thread
3. If unauthorized, `AuthorizationException` is thrown

```php
// This automatically checks ThreadPolicy::sendMessage
Chat::message()->from($user)->to($thread)->text('Hello')->send();

// If user isn't a participant, throws AuthorizationException
```

## ThreadPolicy

| Method | Who Can | Role Required |
|--------|---------|---------------|
| `view` | View thread and messages | Any participant |
| `sendMessage` | Send new messages | Active participant |
| `addParticipant` | Add new members | Admin or Owner |
| `removeParticipant` | Remove members | Admin or Owner |
| `update` | Edit thread name/metadata | Admin or Owner |
| `delete` | Delete entire thread | Owner only |
| `leave` | Leave the thread | Any active participant |

## MessagePolicy

| Method | Who Can | Condition |
|--------|---------|-----------|
| `view` | See message | Participant + not deleted for them |
| `edit` | Edit message | Sender only + not deleted |
| `delete` | Delete for everyone | Sender OR Admin/Owner |
| `deleteForSelf` | Hide from self | Any participant |
| `react` | Add reaction | Participant + message not deleted |

## Manual Checking

Check before showing UI elements:

```php
use Ritechoice23\ChatEngine\Support\PolicyChecker;

$checker = new PolicyChecker;

// Check without throwing
if ($checker->check($user, 'edit', $message)) {
    // Show edit button
}

// Check and throw if unauthorized
$checker->authorize($user, 'delete', $message);

// Via facade
Chat::policy()->check($user, 'sendMessage', $thread);
Chat::policy()->authorize($user, 'addParticipant', $thread);
```

## Custom Policies

### Override Specific Rules

Use Laravel's Gate to override default policies:

```php
// app/Providers/AppServiceProvider.php
use Illuminate\Support\Facades\Gate;
use Ritechoice23\ChatEngine\Models\Thread;

public function boot(): void
{
    // Only verified users can send messages
    Gate::define('chat.thread.sendMessage', function ($user, Thread $thread) {
        if (!$user->hasVerifiedEmail()) {
            return false;
        }
        return $thread->hasParticipant($user);
    });
    
    // Admins can always delete any message
    Gate::define('chat.message.delete', function ($user, $message) {
        return $user->isAdmin() || $message->sender_id === $user->id;
    });
}
```

Gate names follow the pattern: `chat.{model}.{ability}`

### Extending Default Policies

```php
// app/Policies/CustomThreadPolicy.php
use Ritechoice23\ChatEngine\Policies\ThreadPolicy;

class CustomThreadPolicy extends ThreadPolicy
{
    public function sendMessage($user, $thread): bool
    {
        // Add your custom logic
        if ($thread->metadata['locked'] ?? false) {
            return false;
        }
        
        // Fall back to default
        return parent::sendMessage($user, $thread);
    }
}
```

Register in `AppServiceProvider`:

```php
Gate::policy(Thread::class, CustomThreadPolicy::class);
```

## Bypassing Policies

For admin panels or system operations:

```php
use Ritechoice23\ChatEngine\Actions\SendMessage;

$action = new SendMessage;

// With policy check (default)
$action->text($thread, $user, 'Hello');

// Skip policy check
$action->skipAuthorization()->text($thread, $user, 'System message');
```

**Warning**: Only skip authorization for trusted system operations.
