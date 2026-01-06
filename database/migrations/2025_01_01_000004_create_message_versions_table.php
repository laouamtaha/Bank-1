<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('chat-engine.tables.message_versions', 'message_versions'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->index();
            $table->json('payload');
            $table->morphs('edited_by');
            $table->timestamp('created_at');

            $table->index(['message_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('chat-engine.tables.message_versions', 'message_versions'));
    }
};
