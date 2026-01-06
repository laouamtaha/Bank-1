# API Reference

Complete reference for all public classes and methods.

## Facades

### Chat

```php
use Ritechoice23\ChatEngine\Facades\Chat;
```

| Method | Returns | Description |
|--------|---------|-------------|
| `thread()` | `ThreadBuilder` | Start building a thread |
| `message()` | `MessageBuilder` | Start building a message |
| `presence()` | `PresenceManager` | Presence indicators |
| `retention()` | `RetentionManager` | Data cleanup |
| `pipeline()` | `MessagePipeline` | Message processing |
| `policy()` | `PolicyChecker` | Authorization |
| `encryption()` | `EncryptionManager` | Encryption operations |
| `threadsFor($actor)` | `Builder` | Query threads for an actor |
| `unreadCountFor($actor)` | `int` | Unread message count |
| `addParticipant($thread, $actor, $role)` | `ThreadParticipant` | Add to thread |
| `removeParticipant($thread, $actor)` | `bool` | Remove from thread |
| `startTyping($thread, $actor)` | `void` | Dispatch typing event |
| `stopTyping($thread, $actor)` | `void` | Dispatch stop typing event |
| `markThreadAsRead($thread, $actor)` | `int` | Mark all messages read |

---

## Builders

### ThreadBuilder

```php
Chat::thread()
```

| Method | Parameters | Description |
|--------|------------|-------------|
| `between($actorA, $actorB)` | `Model, Model` | Create direct thread |
| `group($name)` | `string` | Create group thread |
| `channel($name)` | `string` | Create channel thread |
| `broadcast($name)` | `string` | Create broadcast thread |
| `type($type)` | `ThreadType` | Set thread type |
| `name($name)` | `?string` | Set thread name |
| `participants($actors)` | `array` | Add participants |
| `metadata($data)` | `array` | Set metadata |
| `create()` | → `Thread` | Persist thread |
| `findOrCreate()` | → `Thread` | Find existing or create |

### MessageBuilder

```php
Chat::message()
```

| Method | Parameters | Description |
|--------|------------|-------------|
| `from($actor)` | `Model` | Set sender |
| `onBehalfOf($actor)` | `Model` | Set author (for proxy sends) |
| `to($thread)` | `Thread` | Set thread |
| `type($type)` | `MessageType` | Set message type |
| `text($content)` | `string` | Text message |
| `image($url, $caption)` | `string, ?string` | Image message |
| `video($url, $thumb, $duration)` | `string, ?string, ?int` | Video message |
| `audio($url, $duration)` | `string, ?int` | Audio message |
| `file($url, $name, $mime, $size)` | `string, string, ?string, ?int` | File message |
| `location($lat, $lng, $address)` | `float, float, ?string` | Location message |
| `contact($name, $phone, $email)` | `string, ?string, ?string` | Contact message |
| `payload($data)` | `array` | Custom payload |
| `send()` | → `Message` | Persist and dispatch events |

---

## Models

### Thread

| Property | Type | Description |
|----------|------|-------------|
| `id` | `int` | Primary key |
| `type` | `string` | Thread type |
| `name` | `?string` | Display name |
| `hash` | `?string` | Participant hash |
| `metadata` | `array` | Custom data |
| `created_at` | `Carbon` | Creation time |
| `updated_at` | `Carbon` | Last update |

| Method | Returns | Description |
|--------|---------|-------------|
| `participants()` | `HasMany` | All participants |
| `activeParticipants()` | `HasMany` | Not left |
| `messages()` | `HasMany` | All messages |
| `latestMessage()` | `HasOne` | Most recent message |
| `hasParticipant($actor)` | `bool` | Check membership |
| `getParticipant($actor)` | `?ThreadParticipant` | Get participant record |

| Scope | Parameters | Description |
|-------|------------|-------------|
| `withParticipant($actor)` | `Model` | Filter by participant |
| `ofType($type)` | `ThreadType` | Filter by type |

### Message

| Property | Type | Description |
|----------|------|-------------|
| `id` | `int` | Primary key |
| `thread_id` | `int` | Parent thread |
| `type` | `string` | Message type |
| `payload` | `array` | Content data |
| `encrypted` | `bool` | Is encrypted |
| `encryption_driver` | `?string` | Encryption driver used |
| `deleted_at` | `?Carbon` | Global deletion time |
| `created_at` | `Carbon` | Send time |

| Method | Returns | Description |
|--------|---------|-------------|
| `thread()` | `BelongsTo` | Parent thread |
| `sender()` | `MorphTo` | Who sent |
| `author()` | `MorphTo` | On whose behalf |
| `versions()` | `HasMany` | Edit history |
| `deliveries()` | `HasMany` | Delivery records |
| `deletions()` | `HasMany` | Per-actor deletions |
| `reactions()` | `MorphMany` | Reactions |
| `currentPayload()` | `array` | Latest content |
| `isEdited()` | `bool` | Has versions |
| `isDeleted()` | `bool` | Globally deleted |
| `isDeletedFor($actor)` | `bool` | Deleted for actor |
| `isDeliveredTo($actor)` | `bool` | Delivered status |
| `isReadBy($actor)` | `bool` | Read status |
| `edit($payload, $editor)` | `?MessageVersion` | Edit message |
| `deleteGlobally($actor)` | `bool` | Delete for all |
| `deleteFor($actor)` | `MessageDeletion` | Delete for self |
| `restore($actor)` | `bool` | Restore deleted |
| `markAsDeliveredTo($actor)` | `MessageDelivery` | Mark delivered |
| `markAsReadBy($actor)` | `MessageDelivery` | Mark read |

| Scope | Parameters | Description |
|-------|------------|-------------|
| `visibleTo($actor)` | `Model` | Exclude deleted |
| `notDeleted()` | - | Exclude globally deleted |
| `inThread($thread)` | `Thread\|int` | Filter by thread |

### ThreadParticipant

| Property | Type | Description |
|----------|------|-------------|
| `id` | `int` | Primary key |
| `thread_id` | `int` | Parent thread |
| `actor_type` | `string` | Morph type |
| `actor_id` | `int` | Morph ID |
| `role` | `string` | Participant role |
| `joined_at` | `Carbon` | Join time |
| `left_at` | `?Carbon` | Leave time |

| Method | Returns | Description |
|--------|---------|-------------|
| `thread()` | `BelongsTo` | Parent thread |
| `actor()` | `MorphTo` | The participant |
| `isActive()` | `bool` | Not left |
| `leave()` | `bool` | Set left_at |

---

## Enums

### ThreadType

```php
use Ritechoice23\ChatEngine\Enums\ThreadType;
```

| Value | Description |
|-------|-------------|
| `DIRECT` | 1-on-1 conversation |
| `GROUP` | Multi-user group |
| `CHANNEL` | Public channel |
| `BROADCAST` | One-to-many |
| `CUSTOM` | Custom type |

### MessageType

```php
use Ritechoice23\ChatEngine\Enums\MessageType;
```

| Value | Description |
|-------|-------------|
| `TEXT` | Text content |
| `IMAGE` | Image attachment |
| `VIDEO` | Video attachment |
| `AUDIO` | Audio attachment |
| `FILE` | File/document |
| `LOCATION` | Geographic location |
| `CONTACT` | Contact card |
| `SYSTEM` | System message |
| `CUSTOM` | Custom type |

### ParticipantRole

```php
use Ritechoice23\ChatEngine\Enums\ParticipantRole;
```

| Value | Description |
|-------|-------------|
| `MEMBER` | Regular participant |
| `ADMIN` | Can manage members |
| `OWNER` | Full control |

### DeletionMode

```php
use Ritechoice23\ChatEngine\Enums\DeletionMode;
```

| Value | Description |
|-------|-------------|
| `SOFT` | Mark as deleted, retain data |
| `HARD` | Physical removal |
| `HYBRID` | Soft default, hard available |

---

## Actions

### CreateThread

```php
use Ritechoice23\ChatEngine\Actions\CreateThread;
```

| Method | Parameters | Returns |
|--------|------------|---------|
| `direct($actorA, $actorB)` | `Model, Model` | `Thread` |
| `group($participants, $name, $metadata)` | `array, ?string, array` | `Thread` |
| `channel($name, $metadata)` | `string, array` | `Thread` |

### SendMessage

```php
use Ritechoice23\ChatEngine\Actions\SendMessage;
```

| Method | Parameters | Returns |
|--------|------------|---------|
| `text($thread, $sender, $content)` | `Thread, Model, string` | `Message` |
| `image($thread, $sender, $url, $caption)` | `Thread, Model, string, ?string` | `Message` |
| `send($thread, $sender, $type, $payload)` | `Thread, Model, MessageType, array` | `Message` |

### EditMessage

```php
use Ritechoice23\ChatEngine\Actions\EditMessage;
```

| Method | Parameters | Returns |
|--------|------------|---------|
| `edit($message, $editor, $payload)` | `Message, Model, array` | `?MessageVersion` |

### DeleteMessage

```php
use Ritechoice23\ChatEngine\Actions\DeleteMessage;
```

| Method | Parameters | Returns |
|--------|------------|---------|
| `globally($message, $actor)` | `Message, Model` | `bool` |
| `forActor($message, $actor)` | `Message, Model` | `MessageDeletion` |
| `restore($message, $actor)` | `Message, Model` | `bool` |

### MarkDelivery

```php
use Ritechoice23\ChatEngine\Actions\MarkDelivery;
```

| Method | Parameters | Returns |
|--------|------------|---------|
| `delivered($message, $actor)` | `Message, Model` | `MessageDelivery` |
| `read($message, $actor)` | `Message, Model` | `MessageDelivery` |

---

## Events

All events extend `ChatEvent` and are dispatched via `dispatch()`.

| Event | Properties |
|-------|------------|
| `ThreadCreated` | `$thread` |
| `ParticipantAdded` | `$thread`, `$actor`, `$participant`, `$role` |
| `ParticipantRemoved` | `$thread`, `$actor`, `$participant` |
| `MessageSent` | `$message`, `$thread`, `$sender` |
| `MessageEdited` | `$message`, `$version`, `$editor` |
| `MessageDelivered` | `$message`, `$actor`, `$delivery` |
| `MessageRead` | `$message`, `$actor`, `$delivery` |
| `MessageDeletedForActor` | `$message`, `$actor`, `$deletion` |
| `MessageDeletedGlobally` | `$message`, `$actor` |
| `MessageRestored` | `$message`, `$actor` |
| `TypingStarted` | `$thread`, `$actor` |
| `TypingStopped` | `$thread`, `$actor` |

---

## API Resources

### ThreadResource

```php
use Ritechoice23\ChatEngine\Resources\ThreadResource;

return new ThreadResource($thread);
```

### MessageResource

```php
use Ritechoice23\ChatEngine\Resources\MessageResource;

MessageResource::viewAs($user);  // Set viewing context
return new MessageResource($message);
```

### Collection Resources

```php
use Ritechoice23\ChatEngine\Resources\ThreadCollection;
use Ritechoice23\ChatEngine\Resources\MessageCollection;

return new ThreadCollection($threads->paginate(20));
return new MessageCollection($messages->cursorPaginate(50));
```
