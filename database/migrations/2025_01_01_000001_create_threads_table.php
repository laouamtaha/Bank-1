<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('chat-engine.tables.threads', 'threads'), function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('name')->nullable();
            $table->string('hash')->unique()->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('chat-engine.tables.threads', 'threads'));
    }
};
