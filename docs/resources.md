# API Resources

Transform models to JSON for API responses. Resources handle relationship loading and formatting.

## Why Use Resources?

- **Consistent structure**: Same JSON format across all endpoints
- **Conditional loading**: Only include relationships when loaded
- **Actor context**: Show delivery/reaction status per user
- **Security**: Control what data is exposed

## Available Resources

| Resource | Model | Use Case |
|----------|-------|----------|
| `ThreadResource` | Thread | Single thread detail |
| `ThreadParticipantResource` | ThreadParticipant | Participant info |
| `MessageResource` | Message | Single message |
| `MessageVersionResource` | MessageVersion | Edit history |
| `MessageDeliveryResource` | MessageDelivery | Delivery records |
| `ThreadCollection` | Thread[] | Thread lists with pagination |
| `MessageCollection` | Message[] | Message lists |

## Basic Usage

```php
use Ritechoice23\ChatEngine\Resources\ThreadResource;
use Ritechoice23\ChatEngine\Resources\MessageResource;

// Single resource
return new ThreadResource($thread);

// Collection
return ThreadResource::collection($threads);

// With pagination
return new ThreadCollection($threads->paginate(20));
```

## Eager Loading

Resources only include relationships when loaded. This prevents N+1 queries:

```php
// Without eager loading - participants not in response
$thread = Thread::find($id);
return new ThreadResource($thread);

// With eager loading - participants included
$thread = Thread::with(['participants.actor', 'latestMessage'])->find($id);
return new ThreadResource($thread);
```

## Setting Viewing Actor

For delivery status and reactions, set the current user:

```php
// Set viewing context
MessageResource::viewAs($currentUser);

// Now resources include actor-specific data
return MessageResource::collection($messages);

// Response includes:
// 'delivery' => ['is_delivered' => true, 'is_read' => false]
// 'reactions' => ['has_reacted' => true, 'user_reaction' => 'love']
```

**Important**: Call this before creating resources, not after.

## ThreadResource Output

```json
{
  "id": 1,
  "type": "direct",
  "name": null,
  "metadata": {"project_id": 123},
  "created_at": "2024-01-01T00:00:00.000000Z",
  "updated_at": "2024-01-01T12:00:00.000000Z",
  
  // Only when participants loaded
  "participants": [
    {"id": 1, "role": "owner", "actor": {...}},
    {"id": 2, "role": "member", "actor": {...}}
  ],
  
  // Only when counted
  "participants_count": 2,
  "messages_count": 42,
  
  // Only when latestMessage loaded
  "latest_message": {"id": 99, "type": "text", ...}
}
```

## MessageResource Output

```json
{
  "id": 1,
  "thread_id": 1,
  "type": "text",
  "payload": {
    "content": "Hello!",
    "mentions": [],
    "urls": []
  },
  "encrypted": false,
  "created_at": "2024-01-01T00:00:00.000000Z",
  
  "sender": {
    "id": 1,
    "type": "App\\Models\\User",
    "name": "John Doe"
  },
  
  // Only when versions loaded
  "is_edited": true,
  "versions_count": 2,
  
  "is_deleted": false,
  
  // Only when viewing actor set
  "delivery": {
    "is_delivered": true,
    "is_read": true
  },
  
  // Only when HasReactions methods exist
  "reactions": {
    "count": 5,
    "breakdown": {"like": 3, "love": 2},
    "has_reacted": true,
    "user_reaction": "love"
  }
}
```

## Controller Examples

### Thread List

```php
class ThreadController extends Controller
{
    public function index(Request $request)
    {
        $threads = Chat::threadsFor($request->user())
            ->with(['latestMessage.sender', 'participants.actor'])
            ->withCount('participants')
            ->latest('updated_at')
            ->paginate(20);
            
        return new ThreadCollection($threads);
    }
}
```

### Thread Detail

```php
public function show(Thread $thread, Request $request)
{
    $this->authorize('view', $thread);
    
    $thread->load(['participants.actor', 'messages' => function ($q) {
        $q->latest()->limit(50);
    }]);
    
    MessageResource::viewAs($request->user());
    
    return new ThreadResource($thread);
}
```

### Message List with Pagination

```php
public function messages(Thread $thread, Request $request)
{
    $this->authorize('view', $thread);
    
    MessageResource::viewAs($request->user());
    
    $messages = $thread->messages()
        ->with(['sender', 'versions'])
        ->latest()
        ->cursorPaginate(50);  // Cursor for infinite scroll
        
    return new MessageCollection($messages);
}
```

### Send Message

```php
public function store(Thread $thread, Request $request)
{
    $message = Chat::message()
        ->from($request->user())
        ->to($thread)
        ->text($request->input('content'))
        ->send();
    
    MessageResource::viewAs($request->user());
    
    return new MessageResource($message->load('sender'));
}
```

## Customizing Resources

Extend to add custom fields:

```php
// app/Http/Resources/CustomMessageResource.php
use Ritechoice23\ChatEngine\Resources\MessageResource;

class CustomMessageResource extends MessageResource
{
    public function toArray($request): array
    {
        return array_merge(parent::toArray($request), [
            'can_edit' => $request->user()?->can('edit', $this->resource),
            'can_delete' => $request->user()?->can('delete', $this->resource),
        ]);
    }
}
```
