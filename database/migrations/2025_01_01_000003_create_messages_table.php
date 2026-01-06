<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('chat-engine.tables.messages', 'messages'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->index();
            $table->morphs('sender');
            $table->nullableMorphs('author');
            $table->string('type');
            $table->json('payload');
            $table->boolean('encrypted')->default(false);
            $table->string('encryption_driver')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->nullableMorphs('deleted_by');
            $table->timestamp('created_at');

            $table->index(['thread_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('chat-engine.tables.messages', 'messages'));
    }
};
