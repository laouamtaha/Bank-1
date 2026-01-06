# Retention & Cleanup

Manage storage by purging old data. Configurable for compliance requirements.

## Why Retention Matters

| Concern | Impact |
|---------|--------|
| **Storage costs** | Message tables grow continuously |
| **Performance** | Large tables slow queries |
| **Compliance** | GDPR may require data deletion |
| **Privacy** | Users expect deleted content to disappear |

## Configuration

```php
// config/chat-engine.php
'retention' => [
    'deleted_messages_days' => 30,   // Purge soft-deleted messages after 30 days
    'delivery_records_days' => 90,   // Purge read receipts after 90 days
],
```

Set to `null` to disable automatic purging.

## Artisan Command

```bash
# Purge messages deleted 30+ days ago
php artisan chat:purge-deleted

# Custom retention period
php artisan chat:purge-deleted --days=7

# Include delivery records
php artisan chat:purge-deleted --deliveries

# Run all cleanup tasks
php artisan chat:purge-deleted --all
```

### What Gets Purged

| Flag | Purges |
|------|--------|
| (default) | Soft-deleted messages past retention |
| `--deliveries` | Read receipts past retention |
| `--all` | All of the above + orphaned deletions |

## Programmatic Usage

```php
use Ritechoice23\ChatEngine\Support\RetentionManager;

$retention = new RetentionManager;
// Or: $retention = Chat::retention();

// Purge by days
$count = $retention->purgeDeletedMessages(30);
echo "Purged $count messages";

// Purge by date
$count = $retention->purgeDeletedMessages(now()->subDays(30));

// Purge old read receipts (reduces table size significantly)
$count = $retention->purgeOldDeliveries(90);

// Purge message versions (edit history)
$count = $retention->purgeOldVersions(180);

// Clean orphaned deletions (messages that were hard-deleted)
$count = $retention->purgeOrphanedDeletions();

// Run all configured cleanup
$results = $retention->runCleanup();
// ['messages' => 150, 'deliveries' => 2000, 'orphaned_deletions' => 12]
```

## Scheduling (Laravel 12)

Run cleanup automatically via task scheduling.

### In routes/console.php

```php
// routes/console.php
use Illuminate\Support\Facades\Schedule;

Schedule::command('chat:purge-deleted --all')
    ->daily()
    ->at('03:00')
    ->withoutOverlapping()
    ->onOneServer();  // For multi-server deployments
```

### In bootstrap/app.php

```php
// bootstrap/app.php
use Illuminate\Console\Scheduling\Schedule;

->withSchedule(function (Schedule $schedule) {
    $schedule->command('chat:purge-deleted --all')
        ->daily()
        ->at('03:00');
})
```

## Compliance Considerations

### GDPR / Right to be Forgotten

When a user requests deletion:

```php
// Hard delete all their messages
$user->sentMessages()->each(function ($message) {
    $message->forceDelete();
});

// Remove from participant records
$user->threadParticipations()->delete();
```

### Audit Requirements

If you need to retain messages for audit:

```php
// Use immutable mode for edit history
'messages' => ['immutable' => true],

// Set long retention or disable
'retention' => ['deleted_messages_days' => null]
```

### Data Export

Before purging, you may want to archive:

```php
// Export thread to JSON before deletion
$export = $thread->load('messages', 'participants')->toJson();
Storage::put("archives/thread-{$thread->id}.json", $export);

// Then purge
$thread->messages()->forceDelete();
$thread->delete();
```

## Monitoring

Track cleanup results:

```php
Schedule::command('chat:purge-deleted --all')
    ->daily()
    ->after(function () {
        Log::info('Chat cleanup completed', [
            'ran_at' => now(),
        ]);
    });
```
