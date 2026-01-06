# Encryption

Message encryption is opt-in. The package provides a driver interface - applications implement their own E2E solutions.

## Architecture

```
Message → EncryptPayload Pipe → Encrypted Storage
                ↓
        EncryptionManager → Driver (laravel, custom, etc.)
```

Key design decisions:
- **Transport agnostic**: Encryption happens at storage layer
- **Driver-based**: Swap implementations without code changes
- **Context-aware**: Drivers receive thread/participant info for key derivation

## Configuration

```php
// config/chat-engine.php
'encryption' => [
    'enabled' => false,         // Toggle encryption
    'driver' => 'none',         // 'none', 'laravel', or custom
],
```

## Built-in Drivers

### NullDriver (`none`)

No encryption - payloads stored as JSON. Default when encryption disabled.

### LaravelDriver (`laravel`)

Uses Laravel's `Crypt` facade (AES-256-CBC). Suitable for **server-side encryption** only.

```php
// Enable Laravel encryption
'encryption' => [
    'enabled' => true,
    'driver' => 'laravel',
],
```

**Note**: This is NOT end-to-end encryption. Server can read all messages. Use for at-rest encryption compliance.

## Enabling Encryption

1. **Configure**:

```php
// config/chat-engine.php
'encryption' => [
    'enabled' => true,
    'driver' => 'laravel',
],
```

2. **Add pipe to pipeline**:

```php
'pipelines' => [
    'message' => [
        \Ritechoice23\ChatEngine\Pipes\SanitizeContent::class,
        \Ritechoice23\ChatEngine\Pipes\DetectMentions::class,
        // ... other pipes
        \Ritechoice23\ChatEngine\Pipes\EncryptPayload::class,  // Add last
    ],
],
```

## How Encrypted Messages Work

When encryption is enabled:

```php
// Before encryption
$message->payload = ['content' => 'Hello world'];
$message->encrypted = false;

// After EncryptPayload pipe
$message->payload = ['_encrypted' => 'base64-encrypted-string...'];
$message->encrypted = true;
$message->encryption_driver = 'laravel';
```

## Decrypting Messages

```php
use Ritechoice23\ChatEngine\Facades\Chat;

if ($message->encrypted) {
    $decrypted = Chat::encryption()->decrypt(
        $message->payload['_encrypted'],
        $message->encryption_driver
    );
    // $decrypted = ['content' => 'Hello world']
}
```

## Custom Encryption Driver

For E2E encryption with client keys:

```php
// app/Encryption/E2EDriver.php
namespace App\Encryption;

use Ritechoice23\ChatEngine\Contracts\EncryptionDriver;

class E2EDriver implements EncryptionDriver
{
    public function encrypt(array $payload, array $context = []): string
    {
        // Get participant public keys from context
        $threadId = $context['thread_id'];
        $participantKeys = $this->getParticipantKeys($threadId);
        
        // Encrypt for each participant (sealed box, etc.)
        $encrypted = $this->encryptForParticipants($payload, $participantKeys);
        
        return base64_encode($encrypted);
    }
    
    public function decrypt(string $encrypted, array $context = []): array
    {
        // Get current user's private key
        $privateKey = $this->getCurrentUserKey();
        
        // Decrypt
        $decrypted = $this->decryptWithKey(base64_decode($encrypted), $privateKey);
        
        return json_decode($decrypted, true);
    }
    
    public function getDriverName(): string
    {
        return 'e2e';
    }
    
    public function canDecrypt(string $driverName): bool
    {
        return $driverName === 'e2e';
    }
}
```

Register in `AppServiceProvider`:

```php
use Ritechoice23\ChatEngine\Facades\Chat;
use App\Encryption\E2EDriver;

public function boot(): void
{
    Chat::encryption()->registerDriver(new E2EDriver);
}
```

Configure:

```php
'encryption' => [
    'enabled' => true,
    'driver' => 'e2e',
],
```

## Context Available to Drivers

The `$context` array passed to `encrypt()`:

```php
[
    'thread_id' => 123,
    'sender_type' => 'App\Models\User',
    'sender_id' => 1,
]
```

Use this for:
- Per-thread key derivation
- Participant-specific encryption
- Audit logging

## Security Considerations

### Server-Side Encryption (LaravelDriver)

- ✅ Protects data at rest
- ✅ Complies with storage encryption requirements
- ❌ Server can read messages
- ❌ Not true E2E

### End-to-End Encryption (Custom Driver)

- ✅ Only participants can read
- ✅ Server cannot decrypt
- ❌ Requires client-side key management
- ❌ More complex implementation
- ❌ Search/moderation limitations

## Migration Strategy

Migrating existing unencrypted messages:

```php
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Facades\Chat;

$encryption = Chat::encryption();

Message::where('encrypted', false)->chunk(100, function ($messages) use ($encryption) {
    foreach ($messages as $message) {
        $encrypted = $encryption->encrypt($message->payload, [
            'thread_id' => $message->thread_id,
        ]);
        
        $message->update([
            'payload' => ['_encrypted' => $encrypted],
            'encrypted' => true,
            'encryption_driver' => $encryption->getDriverName(),
        ]);
    }
});
```
