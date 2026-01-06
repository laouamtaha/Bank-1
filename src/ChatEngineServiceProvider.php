<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class ChatEngineServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('chat-engine')
            ->hasConfigFile()
            ->hasCommand(Commands\PurgeDeletedMessagesCommand::class)
            ->hasMigrations([
                '2025_01_01_000001_create_threads_table',
                '2025_01_01_000002_create_thread_participants_table',
                '2025_01_01_000003_create_messages_table',
                '2025_01_01_000004_create_message_versions_table',
                '2025_01_01_000005_create_message_deliveries_table',
                '2025_01_01_000006_create_message_deletions_table',
                '2025_01_01_000007_create_message_attachments_table',
                '2025_01_01_000008_add_security_columns',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton('chat', function () {
            return new Chat;
        });
    }
}
