# Scaling

Strategies for high-volume chat applications.

## Database Optimization

### Indexes

The package migrations include essential indexes. For high-scale, consider:

```php
// Additional composite index for message listing
Schema::table('messages', function (Blueprint $table) {
    $table->index(['thread_id', 'deleted_at', 'created_at']);
});

// For read receipt queries
Schema::table('message_deliveries', function (Blueprint $table) {
    $table->index(['actor_type', 'actor_id', 'read_at', 'message_id']);
});
```

### Query Optimization

**Avoid N+1 queries**:

```php
// ❌ Bad - N+1
$threads = $user->threads()->get();
foreach ($threads as $thread) {
    $thread->latestMessage; // Query per thread
}

// ✅ Good - Eager load
$threads = $user->threads()
    ->with(['latestMessage.sender', 'participants'])
    ->get();
```

**Use cursor pagination for infinite scroll**:

```php
// ❌ Offset pagination - slow on large tables
$messages = $thread->messages()->paginate(50);

// ✅ Cursor pagination - constant performance
$messages = $thread->messages()
    ->orderBy('id', 'desc')
    ->cursorPaginate(50);
```

### Partitioning

For very large message tables, consider time-based partitioning:

```sql
-- PostgreSQL example
CREATE TABLE messages (
    id BIGSERIAL,
    thread_id BIGINT,
    created_at TIMESTAMP,
    ...
) PARTITION BY RANGE (created_at);

CREATE TABLE messages_2024_q1 PARTITION OF messages
    FOR VALUES FROM ('2024-01-01') TO ('2024-04-01');
```

## Read Replicas

Route heavy read operations to replicas:

```php
// config/database.php
'mysql' => [
    'read' => [
        'host' => ['replica1.example.com', 'replica2.example.com'],
    ],
    'write' => [
        'host' => 'primary.example.com',
    ],
],
```

```php
// Force read replica for thread listing
$threads = User::on('mysql::read')
    ->find($userId)
    ->threads()
    ->get();
```

## Caching

### Thread Metadata

Cache thread info that rarely changes:

```php
use Illuminate\Support\Facades\Cache;

public function getThreadWithCache(int $id): Thread
{
    return Cache::remember("thread:{$id}", 3600, function () use ($id) {
        return Thread::with('participants.actor')->find($id);
    });
}
```

### Unread Counts

Cache unread counts, invalidate on new messages:

```php
// Get cached count
$count = Cache::remember("unread:{$user->id}", 300, function () use ($user) {
    return $user->getUnreadMessagesCount();
});

// Invalidate on new message (in listener)
Event::listen(MessageSent::class, function ($event) {
    foreach ($event->thread->activeParticipants as $participant) {
        Cache::forget("unread:{$participant->actor_id}");
    }
});
```

### Recent Messages

Cache recent messages per thread:

```php
$messages = Cache::remember("thread:{$id}:messages:recent", 60, function () use ($id) {
    return Message::where('thread_id', $id)
        ->with('sender')
        ->latest()
        ->limit(50)
        ->get();
});
```

## Queue Workers

Offload heavy operations to queues:

```php
// Queued listener for notifications
class SendPushNotifications implements ShouldQueue
{
    public $queue = 'chat-notifications';
    
    public function handle(MessageSent $event): void
    {
        // Send to all participants except sender
    }
}

// Dedicated queue worker
// php artisan queue:work --queue=chat-notifications
```

## Real-Time Scaling

### Laravel Reverb

For WebSocket scaling with Laravel Reverb:

```php
// config/reverb.php
'servers' => [
    'reverb' => [
        'host' => '0.0.0.0',
        'port' => 8080,
        'scaling' => [
            'enabled' => true,
            'channel' => 'reverb',
        ],
    ],
],
```

### Pusher/Ably

External services handle horizontal scaling automatically.

### Redis Pub/Sub

For custom WebSocket servers:

```php
// Publish message events to Redis
Event::listen(MessageSent::class, function ($event) {
    Redis::publish('chat:messages', json_encode([
        'thread_id' => $event->thread->id,
        'message' => new MessageResource($event->message),
    ]));
});
```

## Horizontal Scaling

### Stateless Design

The package is stateless by design:
- No in-memory session data
- All state in database
- Events for cross-server communication

### Multi-Server Presence

For presence across multiple servers:

```php
// Use Redis to track online status
Event::listen('chat.presence.online', function ($payload) {
    Redis::sadd("online:users", $payload['actor']->id);
    Redis::expire("online:users", 300);
});

Event::listen('chat.presence.offline', function ($payload) {
    Redis::srem("online:users", $payload['actor']->id);
});

// Check online status
$isOnline = Redis::sismember("online:users", $userId);
```

## Monitoring

### Key Metrics

Track these for scaling decisions:

```php
// Messages per second
$rate = Message::where('created_at', '>', now()->subMinute())->count() / 60;

// Active threads
$activeThreads = Thread::whereHas('messages', function ($q) {
    $q->where('created_at', '>', now()->subHour());
})->count();

// Delivery latency
$avgDelivery = MessageDelivery::whereNotNull('delivered_at')
    ->where('delivered_at', '>', now()->subHour())
    ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, delivered_at)) as avg')
    ->value('avg');
```

### Query Logging

Monitor slow queries:

```php
// In AppServiceProvider
DB::listen(function ($query) {
    if ($query->time > 100) { // > 100ms
        Log::warning('Slow query', [
            'sql' => $query->sql,
            'time' => $query->time,
        ]);
    }
});
```

## Load Testing

Test with realistic patterns:

```php
// Using Pest for load testing
it('handles concurrent message sends', function () {
    $thread = createThread();
    $users = User::factory()->count(10)->create();
    
    $promises = collect($users)->map(function ($user) use ($thread) {
        return async(function () use ($user, $thread) {
            Chat::message()->from($user)->to($thread)->text('Hello')->send();
        });
    });
    
    await($promises);
    
    expect($thread->messages()->count())->toBe(10);
});
```
