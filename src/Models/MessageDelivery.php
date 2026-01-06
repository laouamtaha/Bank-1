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
 * @property \Carbon\Carbon|null $delivered_at
 * @property \Carbon\Carbon|null $read_at
 */
class MessageDelivery extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    /** @phpstan-ignore property.defaultValue (composite primary key - valid Laravel pattern) */
    protected $primaryKey = ['message_id', 'actor_type', 'actor_id'];

    protected $fillable = [
        'message_id',
        'actor_type',
        'actor_id',
        'delivered_at',
        'read_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('chat-engine.tables.message_deliveries', 'message_deliveries');
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

    // Methods

    public function isDelivered(): bool
    {
        return $this->delivered_at !== null;
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markAsDelivered(): bool
    {
        if ($this->delivered_at !== null) {
            return true;
        }

        $this->delivered_at = now();

        return $this->save();
    }

    public function markAsRead(): bool
    {
        if ($this->read_at !== null) {
            return true;
        }

        if ($this->delivered_at === null) {
            $this->delivered_at = now();
        }

        $this->read_at = now();

        return $this->save();
    }

    protected function setKeysForSaveQuery($query)
    {
        return $query
            ->where('message_id', $this->getAttribute('message_id'))
            ->where('actor_type', $this->getAttribute('actor_type'))
            ->where('actor_id', $this->getAttribute('actor_id'));
    }
}
