<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('chat-engine.tables.thread_participants', 'thread_participants'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->index();
            $table->morphs('actor');
            $table->string('role');
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();

            $table->unique(['thread_id', 'actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('chat-engine.tables.thread_participants', 'thread_participants'));
    }
};
