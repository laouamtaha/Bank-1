<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Support;

use Carbon\Carbon;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Models\MessageDelivery;

class RetentionManager
{
    /**
     * Purge soft-deleted messages older than specified date.
     *
     * @return int Number of messages purged
     */
    public function purgeDeletedMessages(Carbon|int $olderThan): int
    {
        $date = $olderThan instanceof Carbon ? $olderThan : now()->subDays($olderThan);

        $messageModel = config('chat-engine.models.message', Message::class);

        return $messageModel::query()
            ->whereNotNull('deleted_at')
            ->where('deleted_at', '<', $date)
            ->delete();
    }

    /**
     * Purge old delivery records.
     *
     * @return int Number of delivery records purged
     */
    public function purgeOldDeliveries(Carbon|int $olderThan): int
    {
        $date = $olderThan instanceof Carbon ? $olderThan : now()->subDays($olderThan);

        return MessageDelivery::query()
            ->whereNotNull('read_at')
            ->where('read_at', '<', $date)
            ->delete();
    }

    /**
     * Purge old message versions.
     *
     * @return int Number of versions purged
     */
    public function purgeOldVersions(Carbon|int $olderThan): int
    {
        $date = $olderThan instanceof Carbon ? $olderThan : now()->subDays($olderThan);

        $versionModel = config('chat-engine.models.message_version', \Ritechoice23\ChatEngine\Models\MessageVersion::class);

        return $versionModel::query()
            ->where('created_at', '<', $date)
            ->delete();
    }

    /**
     * Purge orphaned message deletions (for messages that no longer exist).
     *
     * @return int Number of deletions purged
     */
    public function purgeOrphanedDeletions(): int
    {
        return \Ritechoice23\ChatEngine\Models\MessageDeletion::query()
            ->whereDoesntHave('message')
            ->delete();
    }

    /**
     * Run all retention cleanup tasks.
     *
     * @return array<string, int> Number of records purged per type
     */
    public function runCleanup(): array
    {
        $deletedMessagesDays = config('chat-engine.retention.deleted_messages_days');
        $deliveryRecordsDays = config('chat-engine.retention.delivery_records_days');

        $results = [];

        if ($deletedMessagesDays !== null) {
            $results['messages'] = $this->purgeDeletedMessages($deletedMessagesDays);
        }

        if ($deliveryRecordsDays !== null) {
            $results['deliveries'] = $this->purgeOldDeliveries($deliveryRecordsDays);
        }

        $results['orphaned_deletions'] = $this->purgeOrphanedDeletions();

        return $results;
    }
}
