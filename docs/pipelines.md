# Pipelines

Messages pass through configurable processing pipes before saving. Use for validation, enrichment, filtering.

## How It Works

```
Message → Pipe 1 → Pipe 2 → Pipe 3 → Save to DB
```

Each pipe can:
- **Modify** the message payload
- **Pass** to next pipe
- **Reject** by throwing an exception

Order matters - pipes run sequentially.

## Configuration

```php
// config/chat-engine.php
'pipelines' => [
    'message' => [
        \Ritechoice23\ChatEngine\Pipes\SanitizeContent::class,
        \Ritechoice23\ChatEngine\Pipes\DetectMentions::class,
        \Ritechoice23\ChatEngine\Pipes\DetectUrls::class,
        \Ritechoice23\ChatEngine\Pipes\ValidateMediaUrls::class,
        \Ritechoice23\ChatEngine\Pipes\FilterProfanity::class,
    ],
],
```

## Built-in Pipes

### SanitizeContent

Removes dangerous HTML to prevent XSS attacks.

```php
// Input
'Hello <script>alert("xss")</script> world'

// Output
'Hello alert("xss") world'
```

**When to use**: Always, unless you trust all message sources.

### DetectMentions

Extracts @mentions and adds to payload for highlighting/notifications.

```php
// Input content
'Hello @[John Doe](123) and @jane!'

// Adds to payload
'mentions' => [
    ['name' => 'John Doe', 'id' => 123, 'text' => '@[John Doe](123)'],
    ['username' => 'jane', 'text' => '@jane']
]
```

Supports two formats:
- Markdown style: `@[Display Name](id)` - includes resolved user ID
- Simple: `@username` - just the text, UI resolves

### DetectUrls

Extracts URLs for previews/embeds.

```php
// Input content
'Check out https://example.com/page'

// Adds to payload
'urls' => [
    ['url' => 'https://example.com/page', 'domain' => 'example.com']
]
```

Use this data to fetch OpenGraph previews asynchronously.

### ValidateMediaUrls

Validates URLs in media messages (image, video, audio, file).

```php
// Throws InvalidArgumentException if:
// - URL is empty or invalid format
// - URL uses non-HTTP protocol (file://, javascript:, etc.)
```

**Security**: Prevents malicious URLs in media payloads.

### FilterProfanity

Filters configured words from content.

```php
// Config
'profanity' => [
    'words' => ['badword', 'offensive'],
    'replacement' => '*',
    'mode' => 'asterisk',
],
```

| Mode | Input | Output |
|------|-------|--------|
| `'asterisk'` | `"This is badword"` | `"This is *******"` |
| `'remove'` | `"This is badword"` | `"This is "` |
| `'reject'` | `"This is badword"` | Throws exception |

**Dynamic configuration**:

```php
use Ritechoice23\ChatEngine\Pipes\FilterProfanity;

// Override at runtime
$filter = new FilterProfanity(
    profanityList: ['custom', 'words'],
    replacement: '#',
    mode: 'remove'
);

// Or chain methods
$filter = (new FilterProfanity)
    ->setProfanityList(['word1', 'word2'])
    ->addWords(['word3'])
    ->setMode('reject');
```

## Manual Pipeline Execution

Process a message outside the normal flow:

```php
use Ritechoice23\ChatEngine\Support\MessagePipeline;

$pipeline = new MessagePipeline;
$processedMessage = $pipeline->process($message);
```

## Creating Custom Pipes

```php
// app/Pipes/AddWatermark.php
namespace App\Pipes;

use Closure;
use Ritechoice23\ChatEngine\Contracts\MessagePipe;
use Ritechoice23\ChatEngine\Models\Message;

class AddWatermark implements MessagePipe
{
    public function handle(Message $message, Closure $next): Message
    {
        // Modify payload
        $payload = $message->payload;
        $payload['watermark'] = config('app.name');
        $payload['timestamp'] = now()->toISOString();
        $message->payload = $payload;
        
        // Pass to next pipe
        return $next($message);
    }
}
```

Register in config:

```php
'pipelines' => [
    'message' => [
        \Ritechoice23\ChatEngine\Pipes\SanitizeContent::class,
        \App\Pipes\AddWatermark::class,  // Your pipe
    ],
],
```

### Rejecting Messages

Throw an exception to stop pipeline and reject the message:

```php
class SpamFilter implements MessagePipe
{
    public function handle(Message $message, Closure $next): Message
    {
        if ($this->isSpam($message->payload['content'] ?? '')) {
            throw new \InvalidArgumentException('Message detected as spam.');
        }
        
        return $next($message);
    }
}
```

### Async Processing

For heavy operations, add to payload and process later:

```php
class QueueLinkPreviews implements MessagePipe
{
    public function handle(Message $message, Closure $next): Message
    {
        // Mark for async processing
        $payload = $message->payload;
        $payload['_needs_link_preview'] = true;
        $message->payload = $payload;
        
        // Continue (job dispatched after save via event)
        return $next($message);
    }
}
```
