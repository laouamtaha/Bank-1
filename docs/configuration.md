# Configuration

All options in `config/chat-engine.php`.

## Models

Swap out models to extend behavior:

```php
'models' => [
    'thread' => \Ritechoice23\ChatEngine\Models\Thread::class,
    'message' => \Ritechoice23\ChatEngine\Models\Message::class,
    'thread_participant' => \Ritechoice23\ChatEngine\Models\ThreadParticipant::class,
    'message_version' => \Ritechoice23\ChatEngine\Models\MessageVersion::class,
    'message_delivery' => \Ritechoice23\ChatEngine\Models\MessageDelivery::class,
    'message_deletion' => \Ritechoice23\ChatEngine\Models\MessageDeletion::class,
    'message_attachment' => \Ritechoice23\ChatEngine\Models\MessageAttachment::class,
],
```

**Tip**: Extend the base models to add custom behavior without modifying package code.

## Messages

```php
'messages' => [
    'immutable' => true,
    'deletion_mode' => 'soft',
    'allow_edit_time_limit' => null,
],
```

### `immutable` (default: `true`)

Controls how message edits are handled:

| Value | Behavior | Use Case |
|-------|----------|----------|
| `true` | Edits create new version records. Original preserved. | Compliance, audit trails, "edited" indicators |
| `false` | Payload updated directly. No history. | Simple apps, lower storage |

### `deletion_mode` (default: `'soft'`)

| Value | Behavior | Use Case |
|-------|----------|----------|
| `'soft'` | Marks `deleted_at`, retains data | Recovery, compliance, moderation |
| `'hard'` | Physical removal from database | Privacy-focused, ephemeral messaging |
| `'hybrid'` | Soft by default, hard delete available | Best of both worlds |

### `allow_edit_time_limit`

Minutes after sending that edits are allowed. `null` = unlimited.

```php
'allow_edit_time_limit' => 15, // Can only edit within 15 minutes
```

## Threads

```php
'threads' => [
    'hash_participants' => true,
    'include_roles_in_hash' => true,
    'allow_duplicates' => false,
],
```

### Thread Deduplication

When `hash_participants` is enabled, the package generates a unique hash from participant IDs. This prevents creating duplicate direct threads between the same users.

```php
// First call creates thread
$thread = Chat::thread()->between($userA, $userB)->create();

// Second call returns existing thread (same hash)
$thread2 = Chat::thread()->between($userA, $userB)->create();

// $thread->id === $thread2->id
```

Set `allow_duplicates` to `true` if you need multiple threads between same participants (e.g., "New Conversation" feature).

Set `allow_duplicates` to `true` if you need multiple threads between same participants (e.g., "New Conversation" feature).

## Attachments

```php
'attachments' => [
    'disk' => env('CHAT_FILESYSTEM_DISK', 'public'), // Storage disk
    'path' => 'chat-attachments',                    // Folder path
    'visibility' => 'public',                        // File visibility
    'max_per_message' => 10,                         // Attachment limit
    'allowed_types' => ['image', 'video', 'audio', 'file'],
    'delete_files_on_delete' => false,               // Auto-delete files
    'max_file_size' => null,                         // Max bytes (null = unlimited)
    'allowed_mime_types' => null,                    // Specific MIME types
],
```

## Delivery Tracking

```php
'delivery' => [
    'track_reads' => true,
    'track_deliveries' => true,
],
```

| Setting | When `true` | Use Case |
|---------|-------------|----------|
| `track_deliveries` | Records when message reaches client | "Delivered" checkmarks |
| `track_reads` | Records when recipient opens thread | "Read" / "Seen" indicators |

Disable if you don't need these features to reduce database writes.

## Pipelines

Message processing before save. Order matters - pipes run sequentially:

```php
'pipelines' => [
    'message' => [
        \Ritechoice23\ChatEngine\Pipes\SanitizeContent::class,  // 1. Clean HTML
        \Ritechoice23\ChatEngine\Pipes\DetectMentions::class,   // 2. Find @mentions
        \Ritechoice23\ChatEngine\Pipes\DetectUrls::class,       // 3. Extract URLs
        \Ritechoice23\ChatEngine\Pipes\ValidateMediaUrls::class, // 4. Validate media
        \Ritechoice23\ChatEngine\Pipes\FilterProfanity::class,  // 5. Filter bad words
    ],
],
```

## Profanity Filter

```php
'profanity' => [
    'words' => ['badword1', 'badword2'],
    'replacement' => '*',
    'mode' => 'asterisk',
],
```

| Mode | Result for "This is badword" |
|------|------------------------------|
| `'asterisk'` | `"This is *******"` |
| `'remove'` | `"This is "` |
| `'reject'` | Throws `InvalidArgumentException` |

## Retention

Auto-cleanup settings. See [Retention](retention.md) for scheduling:

```php
'retention' => [
    'deleted_messages_days' => 30,  // Purge soft-deleted after 30 days
    'delivery_records_days' => 90,  // Purge read receipts after 90 days
],
```
