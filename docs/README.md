# Laravel Chat Engine Documentation

A UI-agnostic, transport-agnostic, polymorphic chat engine for Laravel.

## Quick Start

```php
use Ritechoice23\ChatEngine\Facades\Chat;

// Create thread between two users
$thread = Chat::thread()->between($userA, $userB)->create();

// Send a message
$message = Chat::message()->from($userA)->to($thread)->text('Hello!')->send();

// React to message
$userB->react($message, '❤️');
```

## Documentation

### Getting Started

| Topic | Description |
|-------|-------------|
| [Installation](installation.md) | Setup and requirements |
| [Configuration](configuration.md) | All config options |

### Core Features

| Topic | Description |
|-------|-------------|
| [Threads](threads.md) | Creating and managing threads |
| [Messages](messages.md) | Sending and managing messages |
| [Attachments](attachments.md) | Files, images, video, audio |
| [Security](security.md) | Thread locks, PIN protection, E2E verification |
| [Delivery](delivery.md) | Delivery and read receipts |
| [Reactions](reactions.md) | Message reactions |
| [Bookmarks](bookmarks.md) | Save/bookmark messages |

### System

| Topic | Description |
|-------|-------------|
| [Events](events.md) | Domain events |
| [Policies](policies.md) | Authorization |
| [Pipelines](pipelines.md) | Message processing |
| [Presence](presence.md) | Typing indicators |

### Advanced

| Topic | Description |
|-------|-------------|
| [Encryption](encryption.md) | Message encryption |
| [Retention](retention.md) | Data cleanup |
| [Scaling](scaling.md) | Performance optimization |
| [Customization](customization.md) | Extending the package |

### Reference

| Topic | Description |
|-------|-------------|
| [API Resources](resources.md) | JSON transformation |
| [API Reference](api-reference.md) | Complete class/method reference |

## Architecture

- **Actor-First**: Any model can participate via `CanChat` trait
- **Polymorphic**: Supports User, Team, Bot, or any model
- **Event-Driven**: All actions dispatch domain events
- **Configurable**: Immutability, deletion modes, encryption, pipelines
- **Transport-Agnostic**: Integrate with any real-time system
