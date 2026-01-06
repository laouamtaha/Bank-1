<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Ritechoice23\ChatEngine\Enums\AttachmentType;

/**
 * @property int $id
 * @property int $message_id
 * @property string $type
 * @property string $disk
 * @property string $path
 * @property string|null $filename
 * @property string|null $mime_type
 * @property int|null $size
 * @property int|null $duration
 * @property int|null $width
 * @property int|null $height
 * @property string|null $thumbnail_path
 * @property string|null $blurhash
 * @property string|null $caption
 * @property bool $view_once
 * @property \Carbon\Carbon|null $viewed_at
 * @property array|null $metadata
 * @property int $order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read string $url
 * @property-read string|null $thumbnail_url
 * @property-read bool $is_consumed
 * @property-read AttachmentType $type_enum
 */
class MessageAttachment extends Model
{
    protected $fillable = [
        'message_id',
        'type',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
        'duration',
        'width',
        'height',
        'thumbnail_path',
        'blurhash',
        'caption',
        'view_once',
        'viewed_at',
        'metadata',
        'order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'duration' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'view_once' => 'boolean',
            'viewed_at' => 'datetime',
            'metadata' => 'array',
            'order' => 'integer',
        ];
    }

    public function getTable(): string
    {
        return config('chat-engine.tables.message_attachments', 'message_attachments');
    }

    // Relationships

    public function message(): BelongsTo
    {
        return $this->belongsTo(
            config('chat-engine.models.message', Message::class),
            'message_id'
        );
    }

    // Accessors

    /**
     * Get the full URL for this attachment.
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get the thumbnail URL if available.
     */
    public function getThumbnailUrlAttribute(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        return Storage::disk($this->disk)->url($this->thumbnail_path);
    }

    /**
     * Get the attachment type as an enum.
     */
    public function getTypeEnumAttribute(): AttachmentType
    {
        return AttachmentType::from($this->type);
    }

    /**
     * Check if a view_once attachment has been consumed.
     */
    public function getIsConsumedAttribute(): bool
    {
        return $this->view_once && $this->viewed_at !== null;
    }

    // Methods

    /**
     * Mark the attachment as viewed (for view_once attachments).
     * Returns the URL if not yet consumed, null if already viewed.
     */
    public function consume(): ?string
    {
        if (! $this->view_once) {
            return $this->url;
        }

        if ($this->viewed_at !== null) {
            return null; // Already consumed
        }

        $this->viewed_at = now();
        $this->save();

        return $this->url;
    }

    /**
     * Check if this attachment is still accessible.
     */
    public function isAccessible(): bool
    {
        if (! $this->view_once) {
            return true;
        }

        return $this->viewed_at === null;
    }

    /**
     * Get a temporary/signed URL for private disk storage.
     */
    public function temporaryUrl(int $minutes = 60): string
    {
        return Storage::disk($this->disk)->temporaryUrl($this->path, now()->addMinutes($minutes));
    }

    /**
     * Delete the file from storage.
     */
    public function deleteFile(): bool
    {
        $deleted = Storage::disk($this->disk)->delete($this->path);

        if ($this->thumbnail_path) {
            Storage::disk($this->disk)->delete($this->thumbnail_path);
        }

        return $deleted;
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanSizeAttribute(): string
    {
        if (! $this->size) {
            return 'Unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return round($size, 2).' '.$units[$unit];
    }

    /**
     * Get human-readable duration.
     */
    public function getHumanDurationAttribute(): ?string
    {
        if (! $this->duration) {
            return null;
        }

        $minutes = floor($this->duration / 60);
        $seconds = $this->duration % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    protected static function booted(): void
    {
        static::deleting(function (MessageAttachment $attachment) {
            // Optionally delete the file when the attachment record is deleted
            if (config('chat-engine.attachments.delete_files_on_delete', false)) {
                $attachment->deleteFile();
            }
        });
    }
}
