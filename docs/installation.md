# Installation

## Requirements

- PHP 8.2+
- Laravel 11.x or 12.x

## Install

```bash
composer require ritechoice23/laravel-chat-engine
```

## Publish Config

```bash
php artisan vendor:publish --tag="chat-engine-config"
```

## Run Migrations

```bash
php artisan migrate
```

This creates 7 tables:

| Table | Purpose |
|-------|---------|
| `threads` | Conversation containers (direct, group, channel) |
| `thread_participants` | Links actors to threads with roles |
| `messages` | All message content and metadata |
| `message_versions` | Edit history when immutable mode enabled |
| `message_deliveries` | Per-actor delivery/read receipts |
| `message_deletions` | Per-actor soft delete tracking |
| `message_attachments` | File attachments for messages |

## Setup Models

Add `CanChat` trait to any model that participates in conversations. This is polymorphic - works with Users, Teams, Bots, or any Eloquent model:

```php
use Ritechoice23\ChatEngine\Traits\CanChat;

class User extends Authenticatable
{
    use CanChat;
}

class Team extends Model
{
    use CanChat;
}

class Bot extends Model
{
    use CanChat;
}
```

The `CanChat` trait provides:
- `threads()` - All threads the actor participates in
- `sentMessages()` - Messages sent by this actor
- `react()` / `unreact()` - Reaction capabilities (via laravel-reactions)
- `getUnreadMessagesCount()` - Unread message count

## Verify

```php
use Ritechoice23\ChatEngine\Facades\Chat;

$thread = Chat::thread()->between($userA, $userB)->create();
Chat::message()->from($userA)->to($thread)->text('Hello!')->send();
```
