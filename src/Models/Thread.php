<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Ritechoice23\ChatEngine\Enums\ThreadType;

/**
 * @property int $id
 * @property string $type
 * @property string|null $name
 * @property string|null $hash
 * @property array|null $metadata
 * @property bool $is_locked
 * @property array|null $permissions
 * @property \Carbon\Carbon $created_at
 */
class Thread extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'type',
        'name',
        'hash',
        'metadata',
        'is_locked',
        'permissions',
    ];

    protected $attributes = [
        'is_locked' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_locked' => 'boolean',
            'permissions' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('chat-engine.tables.threads', 'threads');
    }

    protected static function booted(): void
    {
        static::deleting(function (Thread $thread) {
            $thread->participants()->delete();
            /** @var Message $message */
            foreach ($thread->messages()->cursor() as $message) {
                $message->delete();
            }
        });

        static::creating(function (Thread $thread) {
            $thread->created_at = $thread->created_at ?? now();
        });
    }

    // Relationships

    /**
     * @return HasMany<ThreadParticipant, $this>
     */
    public function participants(): HasMany
    {
        /** @phpstan-ignore return.type (configurable model class) */
        return $this->hasMany(
            config('chat-engine.models.thread_participant', ThreadParticipant::class),
            'thread_id'
        );
    }

    /**
     * @return HasMany<Message, $this>
     */
    public function messages(): HasMany
    {
        /** @phpstan-ignore return.type (configurable model class) */
        return $this->hasMany(
            config('chat-engine.models.message', Message::class),
            'thread_id'
        );
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(
            config('chat-engine.models.message', Message::class),
            'thread_id'
        )->latestOfMany('created_at');
    }

    // Scopes

    public function scopeOfType(Builder $query, ThreadType|string $type): Builder
    {
        $value = $type instanceof ThreadType ? $type->value : $type;

        return $query->where('type', $value);
    }

    public function scopeWithParticipant(Builder $query, Model $actor): Builder
    {
        return $query->whereHas('participants', function (Builder $q) use ($actor) {
            $q->where('actor_type', $actor->getMorphClass())
                ->where('actor_id', $actor->getKey());
        });
    }

    public function scopeByHash(Builder $query, string $hash): Builder
    {
        return $query->where('hash', $hash);
    }

    // Accessors

    public function getTypeEnumAttribute(): ThreadType
    {
        return ThreadType::from($this->type);
    }

    // Methods

    public function hasParticipant(Model $actor): bool
    {
        return $this->participants()
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey())
            ->whereNull('left_at')
            ->exists();
    }

    public function getParticipant(Model $actor): ?ThreadParticipant
    {
        return $this->participants()
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey())
            ->whereNull('left_at')
            ->first();
    }

    public function activeParticipants(): HasMany
    {
        return $this->participants()->whereNull('left_at');
    }

    public function getUnreadCountFor(Model $actor): int
    {
        return $this->messages()
            ->whereDoesntHave('deliveries', function (Builder $q) use ($actor) {
                $q->where('actor_type', $actor->getMorphClass())
                    ->where('actor_id', $actor->getKey())
                    ->whereNotNull('read_at');
            })
            ->where(function (Builder $q) use ($actor) {
                $q->where('sender_type', '!=', $actor->getMorphClass())
                    ->orWhere('sender_id', '!=', $actor->getKey());
            })
            ->count();
    }

    // Thread Lock Methods

    /**
     * Lock the thread (only admins can send messages).
     */
    public function lock(): bool
    {
        $this->is_locked = true;

        return $this->save();
    }

    /**
     * Unlock the thread (all participants can send messages).
     */
    public function unlock(): bool
    {
        $this->is_locked = false;

        return $this->save();
    }

    /**
     * Check if an actor can send messages to this thread.
     */
    public function canSendMessage(Model $actor): bool
    {
        if (! $this->is_locked) {
            return true;
        }

        $participant = $this->getParticipant($actor);

        if (! $participant) {
            return false;
        }

        // Check if actor has admin/owner role
        return $participant->getRoleEnumAttribute()->canManageParticipants();
    }
}
