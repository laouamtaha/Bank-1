<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $message_id
 * @property array $payload
 * @property string $edited_by_type
 * @property int $edited_by_id
 * @property \Carbon\Carbon $created_at
 */
class MessageVersion extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'message_id',
        'payload',
        'edited_by_type',
        'edited_by_id',
        'created_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('chat-engine.tables.message_versions', 'message_versions');
    }

    protected static function booted(): void
    {
        static::creating(function (MessageVersion $version) {
            $version->created_at = $version->created_at ?? now();
        });
    }

    // Relationships

    public function message(): BelongsTo
    {
        return $this->belongsTo(
            config('chat-engine.models.message', Message::class),
            'message_id'
        );
    }

    public function editedBy(): MorphTo
    {
        return $this->morphTo('edited_by');
    }
}
