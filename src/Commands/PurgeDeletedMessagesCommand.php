<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Commands;

use Illuminate\Console\Command;
use Ritechoice23\ChatEngine\Support\RetentionManager;

class PurgeDeletedMessagesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:purge-deleted
                            {--days=30 : Number of days to retain deleted messages}
                            {--deliveries : Also purge old delivery records}
                            {--all : Run all cleanup tasks}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Purge soft-deleted messages and optionally other old records';

    /**
     * Execute the console command.
     */
    public function handle(RetentionManager $retention): int
    {
        $days = (int) $this->option('days');

        if ($this->option('all')) {
            $this->info('Running all cleanup tasks...');
            $results = $retention->runCleanup();

            foreach ($results as $type => $count) {
                $this->line("  - Purged {$count} {$type}");
            }

            $this->info('Cleanup complete!');

            return self::SUCCESS;
        }

        $this->info("Purging messages deleted more than {$days} days ago...");
        $count = $retention->purgeDeletedMessages($days);
        $this->line("  - Purged {$count} messages");

        if ($this->option('deliveries')) {
            $this->info("Purging delivery records older than {$days} days...");
            $deliveryCount = $retention->purgeOldDeliveries($days);
            $this->line("  - Purged {$deliveryCount} delivery records");
        }

        $this->info('Done!');

        return self::SUCCESS;
    }
}
