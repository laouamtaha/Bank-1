<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add security columns to threads table
        Schema::table(config('chat-engine.tables.threads', 'threads'), function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('metadata');
            $table->json('permissions')->nullable()->after('is_locked');
        });

        // Add security columns to thread_participants table
        Schema::table(config('chat-engine.tables.thread_participants', 'thread_participants'), function (Blueprint $table) {
            $table->string('chat_lock_pin')->nullable()->after('left_at');
            $table->text('public_key')->nullable()->after('chat_lock_pin');
            $table->string('security_code')->nullable()->after('public_key');
        });
    }

    public function down(): void
    {
        Schema::table(config('chat-engine.tables.threads', 'threads'), function (Blueprint $table) {
            $table->dropColumn(['is_locked', 'permissions']);
        });

        Schema::table(config('chat-engine.tables.thread_participants', 'thread_participants'), function (Blueprint $table) {
            $table->dropColumn(['chat_lock_pin', 'public_key', 'security_code']);
        });
    }
};
