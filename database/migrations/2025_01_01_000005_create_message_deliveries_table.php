<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('chat-engine.tables.message_deliveries', 'message_deliveries'), function (Blueprint $table) {
            $table->foreignId('message_id')->index();
            $table->morphs('actor');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();

            $table->primary(['message_id', 'actor_type', 'actor_id']);
            $table->index(['actor_type', 'actor_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('chat-engine.tables.message_deliveries', 'message_deliveries'));
    }
};
