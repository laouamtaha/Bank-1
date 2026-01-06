# Security Features

Advanced security and privacy controls for chat threads and messages.

## Overview

The package provides three distinct security layers:

1. **Global Thread Lock** — Admin-controlled read-only mode for threads
2. **Personal Chat Lock (PIN)** — User-specific PIN protection for thread access
3. **E2E Verification** — Public key verification for end-to-end encryption

---

## Global Thread Lock

Lock a thread so only admins can send messages. Perfect for announcement channels or moderated discussions.

### Locking a Thread

```php
use Ritechoice23\ChatEngine\Facades\Chat;

$thread = Chat::thread()->channel('announcements')->create();

// Lock the thread
$thread->lock();

// Check if locked
if ($thread->is_locked) {
    // Thread is in read-only mode
}
```

### Unlocking a Thread

```php
$thread->unlock();
```

### Sending Messages to Locked Threads

```php
// Admin can send
Chat::message()
    ->from($admin)
    ->to($thread)
    ->text('Important announcement')
    ->send(); // ✅ Works

// Regular member cannot send
Chat::message()
    ->from($member)
    ->to($thread)
    ->text('Reply')
    ->send(); // ❌ Throws InvalidArgumentException
```

### Checking Send Permissions

```php
if ($thread->canSendMessage($user)) {
    // User can send messages
}
```

### Authorization

Only participants with `admin` or `owner` roles can:
- Lock/unlock threads
- Send messages to locked threads

```php
use Ritechoice23\ChatEngine\Enums\ParticipantRole;

// Make user an admin
$participant = $thread->getParticipant($user);
$participant->update(['role' => ParticipantRole::ADMIN->value]);

// Now they can lock the thread
$thread->lock();
```

---

## Personal Chat Lock (PIN)

Users can protect their view of a specific thread with a PIN. This is a **client-side privacy feature** — the chat is hidden until the correct PIN is entered.

### Setting a PIN

```php
$participant = $thread->getParticipant($user);

// Lock with 4-6 digit PIN
$participant->lockChat('1234');
```

### Verifying PIN

```php
if ($participant->checkPin('1234')) {
    // Correct PIN - show chat
} else {
    // Wrong PIN - deny access
}
```

### Checking Lock Status

```php
if ($participant->isChatLocked()) {
    // Prompt for PIN
}
```

### Removing PIN

```php
$participant->unlockChat();
```

### Security Notes

- PINs are hashed using Laravel's `Hash` facade (bcrypt/argon2)
- Never stored in plain text
- No "forgot PIN" recovery by default (security by design)
- Each user has their own PIN per thread

---

## E2E Verification (Security Codes)

Verify that your encryption keys match with another user to prevent Man-in-the-Middle (MITM) attacks.

### Setting Public Keys

```php
$participantA = $thread->getParticipant($userA);
$participantB = $thread->getParticipant($userB);

// Set public keys (from your E2E encryption system)
$participantA->setPublicKey($userA_publicKey);
$participantB->setPublicKey($userB_publicKey);
```

### Generating Security Codes

When you set a public key, a 60-digit security code is automatically generated:

```php
$participantA->setPublicKey('MIGfMA0GCSqGSIb3DQEBAQUAA4GN...');

// Security code is generated
echo $participantA->security_code; 
// "123456789012345678901234567890123456789012345678901234567890"
```

### Displaying Security Codes

```php
// Formatted for display (12 groups of 5 digits)
echo $participantA->formatted_security_code;
// "12345 67890 12345 67890 12345 67890 12345 67890 12345 67890 12345 67890"
```

### Verifying Between Users

```php
if ($participantA->verifySecurityWith($participantB)) {
    // Security codes match - connection is secure
} else {
    // Codes don't match - possible MITM attack
}
```

### Usage Flow

1. Both users set their public keys
2. System generates deterministic security codes
3. Users compare codes out-of-band (QR code, phone call, etc.)
4. If codes match, encryption is verified

---

## API Resources

Security data is included in API responses:

### ThreadResource

```json
{
    "id": 1,
    "type": "channel",
    "is_locked": true,
    "permissions": null,
    "participants": [...]
}
```

### ThreadParticipantResource

```php
// Include security data when needed
$participant->load('actor');

return new ThreadParticipantResource($participant);
```

```json
{
    "id": 1,
    "role": "member",
    "is_active": true,
    "chat_lock_enabled": true,
    "has_public_key": true
}
```

---

## Configuration

```php
// config/chat-engine.php

'models' => [
    'thread' => \Ritechoice23\ChatEngine\Models\Thread::class,
    'thread_participant' => \Ritechoice23\ChatEngine\Models\ThreadParticipant::class,
],
```

---

## Database Schema

### Threads Table

| Column | Type | Description |
|--------|------|-------------|
| `is_locked` | boolean | Global lock status |
| `permissions` | json | Flexible permissions (future use) |

### Thread Participants Table

| Column | Type | Description |
|--------|------|-------------|
| `chat_lock_pin` | string | Hashed PIN for personal lock |
| `public_key` | text | Public key for E2E verification |
| `security_code` | string | 60-digit verification code |

---

## Use Cases

### Announcement Channels

```php
$channel = Chat::thread()->channel('updates')->create();
$channel->lock();

// Only admins can post
Chat::message()->from($admin)->to($channel)->text('New feature released!')->send();
```

### Secret Chats

```php
$participant = $thread->getParticipant($user);
$participant->lockChat('9876');

// User must enter PIN to view chat
if (!$participant->checkPin($inputPin)) {
    abort(403, 'Incorrect PIN');
}
```

### Verified Encryption

```php
// After setting up E2E encryption
$participantA->setPublicKey($keyA);
$participantB->setPublicKey($keyB);

// Display QR codes with security codes
return view('verify', [
    'code' => $participantA->formatted_security_code
]);
```

---

## Events

Security actions dispatch standard events:

```php
use Ritechoice23\ChatEngine\Events\MessageSent;

// Locked thread blocks non-admins before MessageSent is dispatched
```

---

## Best Practices

1. **Global Lock**: Use for channels, announcements, or moderated groups
2. **PIN Lock**: Implement UI for PIN entry/setup on client side
3. **E2E Verification**: Display codes as QR codes for easy comparison
4. **Permissions**: Store custom permission rules in the `permissions` JSON column

---

## Security Considerations

- **PIN Storage**: Always hashed, never reversible
- **Public Keys**: Store only public keys, never private keys
- **Verification**: Security codes are deterministic but unique per key pair
- **Thread Lock**: Enforced at the application layer before message creation
