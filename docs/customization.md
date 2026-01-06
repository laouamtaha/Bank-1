# Customization

Extend and customize package behavior without modifying source code.

## Custom Models

### Extending Models

Create your own model that extends the package model:

```php
// app/Models/Thread.php
namespace App\Models;

use Ritechoice23\ChatEngine\Models\Thread as BaseThread;

class Thread extends BaseThread
{
    // Add custom relationships
    public function project()
    {
        return $this->belongsTo(Project::class, 'metadata->project_id');
    }
    
    // Add custom scopes
    public function scopeForProject($query, $projectId)
    {
        return $query->where('metadata->project_id', $projectId);
    }
    
    // Override methods
    public function toArray()
    {
        return array_merge(parent::toArray(), [
            'project_name' => $this->project?->name,
        ]);
    }
}
```

### Register Custom Model

```php
// config/chat-engine.php
'models' => [
    'thread' => \App\Models\Thread::class,
    'message' => \App\Models\Message::class,
    // ... etc
],
```

The package uses these config values for all model operations.

## Custom Message Types

### Define New Type

```php
// app/Enums/CustomMessageType.php
namespace App\Enums;

enum CustomMessageType: string
{
    case POLL = 'poll';
    case CARD = 'card';
    case EMBED = 'embed';
    case PAYMENT = 'payment';
}
```

### Use in Messages

```php
use Ritechoice23\ChatEngine\Enums\MessageType;

// Using built-in custom type
Chat::message()
    ->from($user)
    ->to($thread)
    ->type(MessageType::CUSTOM)
    ->payload([
        'custom_type' => 'poll',
        'question' => 'Lunch preference?',
        'options' => ['Pizza', 'Sushi', 'Salad'],
        'votes' => [],
    ])
    ->send();
```

### Validate Custom Payloads

Create a custom pipe:

```php
// app/Pipes/ValidateCustomPayload.php
namespace App\Pipes;

use Closure;
use InvalidArgumentException;
use Ritechoice23\ChatEngine\Contracts\MessagePipe;
use Ritechoice23\ChatEngine\Models\Message;

class ValidateCustomPayload implements MessagePipe
{
    public function handle(Message $message, Closure $next): Message
    {
        if ($message->type !== 'custom') {
            return $next($message);
        }
        
        $customType = $message->payload['custom_type'] ?? null;
        
        match ($customType) {
            'poll' => $this->validatePoll($message->payload),
            'card' => $this->validateCard($message->payload),
            default => throw new InvalidArgumentException("Unknown custom type: {$customType}"),
        };
        
        return $next($message);
    }
    
    protected function validatePoll(array $payload): void
    {
        if (empty($payload['question'])) {
            throw new InvalidArgumentException('Poll requires a question');
        }
        if (empty($payload['options']) || count($payload['options']) < 2) {
            throw new InvalidArgumentException('Poll requires at least 2 options');
        }
    }
}
```

Register in config:

```php
'pipelines' => [
    'message' => [
        \Ritechoice23\ChatEngine\Pipes\SanitizeContent::class,
        \App\Pipes\ValidateCustomPayload::class,  // Your pipe
    ],
],
```

## Custom Thread Types

### Use Custom Type

```php
use Ritechoice23\ChatEngine\Enums\ThreadType;

// Built-in custom type
$thread = Chat::thread()
    ->type(ThreadType::CUSTOM)
    ->metadata([
        'custom_type' => 'support_ticket',
        'ticket_id' => 'TKT-12345',
        'priority' => 'high',
    ])
    ->participants([$customer, $agent])
    ->create();
```

### Add Custom Scopes

```php
// app/Models/Thread.php
class Thread extends BaseThread
{
    public function scopeSupportTickets($query)
    {
        return $query->where('metadata->custom_type', 'support_ticket');
    }
    
    public function scopeHighPriority($query)
    {
        return $query->where('metadata->priority', 'high');
    }
}

// Usage
$urgentTickets = Thread::supportTickets()->highPriority()->get();
```

## Custom Pipes

### Pipe Interface

```php
namespace Ritechoice23\ChatEngine\Contracts;

interface MessagePipe
{
    public function handle(Message $message, Closure $next): Message;
}
```

### Example: Link Previews

```php
// app/Pipes/FetchLinkPreviews.php
namespace App\Pipes;

use Closure;
use Ritechoice23\ChatEngine\Contracts\MessagePipe;
use Ritechoice23\ChatEngine\Models\Message;

class FetchLinkPreviews implements MessagePipe
{
    public function handle(Message $message, Closure $next): Message
    {
        $urls = $message->payload['urls'] ?? [];
        
        if (empty($urls)) {
            return $next($message);
        }
        
        // Queue preview fetching (don't block message send)
        dispatch(new FetchLinkPreviewsJob($message->id, $urls));
        
        // Mark as pending
        $payload = $message->payload;
        $payload['link_previews_pending'] = true;
        $message->payload = $payload;
        
        return $next($message);
    }
}
```

### Example: Rate Limiting

```php
// app/Pipes/RateLimitMessages.php
namespace App\Pipes;

use Closure;
use Illuminate\Support\Facades\RateLimiter;
use Ritechoice23\ChatEngine\Contracts\MessagePipe;
use Ritechoice23\ChatEngine\Models\Message;

class RateLimitMessages implements MessagePipe
{
    public function handle(Message $message, Closure $next): Message
    {
        $key = "chat:send:{$message->sender_type}:{$message->sender_id}";
        
        if (RateLimiter::tooManyAttempts($key, 30)) { // 30 per minute
            throw new \RuntimeException('Too many messages. Please slow down.');
        }
        
        RateLimiter::hit($key, 60);
        
        return $next($message);
    }
}
```

## Custom Policies

### Extend Default Policy

```php
// app/Policies/ThreadPolicy.php
namespace App\Policies;

use Ritechoice23\ChatEngine\Policies\ThreadPolicy as BasePolicy;

class ThreadPolicy extends BasePolicy
{
    public function sendMessage($user, $thread): bool
    {
        // Check thread lock
        if ($thread->metadata['locked'] ?? false) {
            return $user->isAdmin();
        }
        
        // Check ban
        if ($this->isUserBannedFromThread($user, $thread)) {
            return false;
        }
        
        return parent::sendMessage($user, $thread);
    }
    
    protected function isUserBannedFromThread($user, $thread): bool
    {
        return ThreadBan::where('user_id', $user->id)
            ->where('thread_id', $thread->id)
            ->exists();
    }
}
```

Register:

```php
// AppServiceProvider::boot()
use Illuminate\Support\Facades\Gate;

Gate::policy(Thread::class, \App\Policies\ThreadPolicy::class);
```

## Custom Events

### Listen and Transform

```php
// AppServiceProvider::boot()
Event::listen(MessageSent::class, function ($event) {
    // Dispatch your own event with additional data
    YourMessageSent::dispatch(
        $event->message,
        $event->thread,
        $event->sender,
        $this->enrichWithMetadata($event)
    );
});
```

### Prevent Default Behavior

```php
// Prevent resource-based actions on certain threads
Event::listen(MessageSent::class, function ($event) {
    if ($event->thread->metadata['archive'] ?? false) {
        throw new \Exception('Cannot send to archived thread');
    }
});
```

## Custom Resources

### Extend API Resources

```php
// app/Http/Resources/MessageResource.php
namespace App\Http\Resources;

use Ritechoice23\ChatEngine\Resources\MessageResource as BaseResource;

class MessageResource extends BaseResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'can_edit' => $request->user()?->can('edit', $this->resource),
            'can_delete' => $request->user()?->can('delete', $this->resource),
            'custom_meta' => $this->extractCustomMeta(),
        ]);
    }
    
    protected function extractCustomMeta(): array
    {
        // Add app-specific fields
        return [
            'read_by_count' => $this->resource->deliveries()->whereNotNull('read_at')->count(),
        ];
    }
}
```

## Configuration Override

### Dynamic Configuration

```php
// Change config at runtime
config()->set('chat-engine.messages.immutable', false);

// Thread-specific settings via metadata
$thread->metadata['settings'] = [
    'allow_reactions' => false,
    'max_message_length' => 500,
];
```

### Per-Thread Rules

Check thread metadata in pipes:

```php
class ApplyThreadRules implements MessagePipe
{
    public function handle(Message $message, Closure $next): Message
    {
        $settings = $message->thread->metadata['settings'] ?? [];
        
        // Max length
        if ($maxLength = $settings['max_message_length'] ?? null) {
            $content = $message->payload['content'] ?? '';
            if (strlen($content) > $maxLength) {
                throw new \InvalidArgumentException("Message exceeds {$maxLength} characters");
            }
        }
        
        return $next($message);
    }
}
```
