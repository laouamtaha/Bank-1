# Threads

A thread is a conversation container. It holds participants and messages.

## Thread Types

| Type | Participants | Use Case |
|------|--------------|----------|
| `direct` | Exactly 2 | 1-on-1 private conversations |
| `group` | 2+ | Team chats, project discussions |
| `channel` | Many | Public channels, announcements |
| `broadcast` | Many (1 sender) | System notifications, newsletters |
| `custom` | Any | Your own implementation |

## Creating Threads

### Direct Thread (1-on-1)

```php
$thread = Chat::thread()
    ->between($userA, $userB)
    ->create();
```

**Important**: Direct threads are deduplicated by default. Calling `between($userA, $userB)` twice returns the same thread. This prevents "duplicate conversation" bugs.

### Group Thread

```php
$thread = Chat::thread()
    ->group('Engineering Team')
    ->participants([$user1, $user2, $user3])
    ->metadata(['project_id' => 123])
    ->create();
```

**Note**: Group threads are NOT deduplicated. Each call creates a new thread, even with same participants.

### Channel Thread

```php
$thread = Chat::thread()
    ->channel('announcements')
    ->create();
```

Channels are best for many readers, few writers. Consider adding role-based sending permissions.

### Participants with Roles

Roles control permissions (see [Policies](policies.md)):

```php
use Ritechoice23\ChatEngine\Enums\ParticipantRole;

$thread = Chat::thread()
    ->type(ThreadType::GROUP)
    ->participants([
        ['actor' => $admin, 'role' => ParticipantRole::OWNER],   // Full control
        ['actor' => $mod, 'role' => ParticipantRole::ADMIN],     // Can manage members
        ['actor' => $user, 'role' => ParticipantRole::MEMBER],   // Can send messages
    ])
    ->name('Project Chat')
    ->create();
```

| Role | Send | Delete Own | Manage Members | Delete Thread |
|------|------|------------|----------------|---------------|
| `MEMBER` | ✓ | ✓ | ✗ | ✗ |
| `ADMIN` | ✓ | ✓ | ✓ | ✗ |
| `OWNER` | ✓ | ✓ | ✓ | ✓ |

## Managing Participants

```php
// Add participant
Chat::addParticipant($thread, $newUser, ParticipantRole::MEMBER);

// Remove participant (marks left_at, doesn't delete record)
Chat::removeParticipant($thread, $user);

// Check participation
$user->isParticipantIn($thread); // bool

// Get participant record
$participant = $thread->getParticipant($user);
$participant->role;      // ParticipantRole enum
$participant->isActive(); // true if not left
$participant->joined_at;
$participant->left_at;   // null if still active
```

### Participant Lifecycle

When a participant leaves, `left_at` is set rather than deleting the record. This allows:
- Viewing historical messages they sent
- Rejoining the thread later
- Audit trails

## Querying Threads

```php
// All threads for an actor (including left)
$threads = $user->threads()->get();

// Active threads only (not left)
$threads = $user->activeThreads()->get();

// Via facade
$threads = Chat::threadsFor($user)->get();

// With eager loading (recommended for lists)
$threads = $user->activeThreads()
    ->with(['latestMessage.sender', 'participants.actor'])
    ->withCount('participants', 'messages')
    ->latest('updated_at')
    ->paginate(20);

// Find direct thread with specific user
$thread = $user->getDirectThreadWith($otherUser);
if ($thread) {
    // Existing conversation
} else {
    // No conversation yet
}
```

## Thread Methods

```php
// Relationships
$thread->participants();       // All participant records
$thread->activeParticipants(); // Not left
$thread->messages();           // All messages
$thread->latestMessage();      // Most recent (for previews)

// Checks
$thread->hasParticipant($actor);    // Is actor in thread?
$thread->getParticipant($actor);    // Get participant record
```

## Thread Metadata

Store custom data on threads:

```php
$thread = Chat::thread()
    ->group('Support Ticket #1234')
    ->metadata([
        'ticket_id' => 1234,
        'priority' => 'high',
        'department' => 'billing',
    ])
    ->create();

// Access later
$ticketId = $thread->metadata['ticket_id'];
```
