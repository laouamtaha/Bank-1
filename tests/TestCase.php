<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;
use Ritechoice23\ChatEngine\ChatEngineServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Ritechoice23\\ChatEngine\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app): array
    {
        return [
            ChatEngineServiceProvider::class,
            \Ritechoice23\Reactions\ReactionsServiceProvider::class,
            \Ritechoice23\Saveable\SaveableServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Load reactions package migrations
        $reactionsPath = __DIR__.'/../vendor/ritechoice23/laravel-reactions/database/migrations';
        if (is_dir($reactionsPath)) {
            $this->loadMigrationsFrom($reactionsPath);
        }

        // Load saveable package migrations
        $saveablePath = __DIR__.'/../vendor/ritechoice23/laravel-saveable/database/migrations';
        if (is_dir($saveablePath)) {
            $this->loadMigrationsFrom($saveablePath);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set app key for encryption tests
        config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }
}
