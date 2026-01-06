<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('chat-engine.tables.message_deletions', 'message_deletions'), function (Blueprint $table) {
            $table->foreignId('message_id')->index();
            $table->morphs('actor');
            $table->timestamp('deleted_at');

            $table->primary(['message_id', 'actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('chat-engine.tables.message_deletions', 'message_deletions'));
    }
};
