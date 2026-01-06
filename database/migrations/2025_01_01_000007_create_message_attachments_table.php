<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('chat-engine.tables.message_attachments', 'message_attachments'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->index();
            $table->string('type');                           // image, video, audio, file
            $table->string('disk');                           // storage disk (e.g., s3, public)
            $table->string('path');                           // relative path on disk
            $table->string('filename')->nullable();           // original filename
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();   // bytes
            $table->unsignedInteger('duration')->nullable();  // seconds (video/audio)
            $table->unsignedInteger('width')->nullable();     // pixels (image/video)
            $table->unsignedInteger('height')->nullable();    // pixels (image/video)
            $table->string('thumbnail_path')->nullable();     // thumbnail path on same disk
            $table->string('blurhash')->nullable();           // BlurHash placeholder string
            $table->string('caption')->nullable();
            $table->boolean('view_once')->default(false);     // self-destruct after viewing
            $table->timestamp('viewed_at')->nullable();       // when view_once was consumed
            $table->json('metadata')->nullable();             // extensible custom data
            $table->unsignedInteger('order')->default(0);     // display order in message
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('chat-engine.tables.message_attachments', 'message_attachments'));
    }
};
