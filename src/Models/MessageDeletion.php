<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $message_id
 * @property string $actor_type
 * @property int $actor_id
 * @property \Carbon\Carbon $deleted_at
 */
class MessageDeletion extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    /** @phpstan-ignore property.defaultValue (composite primary key - valid Laravel pattern) */
    protected $primaryKey = ['message_id', 'actor_type', 'actor_id'];

    protected $fillable = [
        'message_id',
        'actor_type',
        'actor_id',
        'deleted_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('chat-engine.tables.message_deletions', 'message_deletions');
    }

    protected static function booted(): void
    {
        static::creating(function (MessageDeletion $deletion) {
            $deletion->deleted_at = $deletion->deleted_at ?? now();
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

    public function actor(): MorphTo
    {
        return $this->morphTo('actor');
    }

    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('message_id', $this->getAttribute('message_id'))
            ->where('actor_type', $this->getAttribute('actor_type'))
            ->where('actor_id', $this->getAttribute('actor_id'));
    }
}
