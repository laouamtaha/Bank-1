<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Ritechoice23\ChatEngine\Enums\MessageType;
use Ritechoice23\ChatEngine\Events\MessageDeletedForActor;
use Ritechoice23\ChatEngine\Events\MessageDeletedGlobally;
use Ritechoice23\ChatEngine\Events\MessageDelivered;
use Ritechoice23\ChatEngine\Events\MessageEdited;
use Ritechoice23\ChatEngine\Events\MessageRead;
use Ritechoice23\ChatEngine\Events\MessageRestored;
use Ritechoice23\Reactions\Traits\HasReactions;
use Ritechoice23\Saveable\Traits\IsSaveable;

/**
 * @property int $id
 * @property int $thread_id
 * @property string $sender_type
 * @property int $sender_id
 * @property string|null $author_type
 * @property int|null $author_id
 * @property string $type
 * @property array $payload
 * @property bool $encrypted
 * @property string|null $encryption_driver
 * @property \Carbon\Carbon|null $deleted_at
 * @property string|null $deleted_by_type
 * @property int|null $deleted_by_id
 * @property \Carbon\Carbon $created_at
 * @property-read Thread $thread
 * @property-read bool $is_edited
 * @property-read bool $is_deleted
 * @property-read array $current_payload
 * @property-read MessageType $type_enum
 */
class Message extends Model
{
    use HasReactions;
    use IsSaveable;

    public $timestamps = false;

    protected $fillable = [
        'thread_id',
        'sender_type',
        'sender_id',
        'author_type',
        'author_id',
        'type',
        'payload',
        'encrypted',
        'encryption_driver',
        'deleted_at',
        'deleted_by_type',
        'deleted_by_id',
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
            'encrypted' => 'boolean',
            'deleted_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('chat-engine.tables.messages', 'messages');
    }

    protected static function booted(): void
    {
        static::deleting(function (Message $message) {
            $message->versions()->delete();
            $message->deliveries()->delete();
            $message->deletions()->delete();
            $message->attachments()->each(fn ($a) => $a->delete());
        });

        static::creating(function (Message $message) {
            $message->created_at = $message->created_at ?? now();
            $message->type = $message->type ?? MessageType::TEXT->value;
        });
    }

    // Relationships

    /**
     * @return BelongsTo<Thread, $this>
     */
    public function thread(): BelongsTo
    {
        /** @phpstan-ignore return.type (configurable model class) */
        return $this->belongsTo(
            config('chat-engine.models.thread', Thread::class),
            'thread_id'
        );
    }

    public function sender(): MorphTo
    {
        return $this->morphTo('sender');
    }

    public function author(): MorphTo
    {
        return $this->morphTo('author');
    }

    /**
     * @return HasMany<MessageVersion, $this>
     */
    public function versions(): HasMany
    {
        /** @phpstan-ignore return.type (configurable model class) */
        return $this->hasMany(
            config('chat-engine.models.message_version', MessageVersion::class),
            'message_id'
        );
    }

    /**
     * @return HasMany<MessageDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        /** @phpstan-ignore return.type (configurable model class) */
        return $this->hasMany(
            config('chat-engine.models.message_delivery', MessageDelivery::class),
            'message_id'
        );
    }

    /**
     * @return HasMany<MessageDeletion, $this>
     */
    public function deletions(): HasMany
    {
        /** @phpstan-ignore return.type (configurable model class) */
        return $this->hasMany(
            config('chat-engine.models.message_deletion', MessageDeletion::class),
            'message_id'
        );
    }

    public function deletedBy(): MorphTo
    {
        return $this->morphTo('deleted_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            config('chat-engine.models.message_attachment', MessageAttachment::class),
            'message_id'
        )->orderBy('order');
    }

    // Scopes

    public function scopeInThread(Builder $query, Thread|int $thread): Builder
    {
        $threadId = $thread instanceof Thread ? $thread->id : $thread;

        return $query->where('thread_id', $threadId);
    }

    public function scopeFromSender(Builder $query, Model $actor): Builder
    {
        return $query->where('sender_type', $actor->getMorphClass())
            ->where('sender_id', $actor->getKey());
    }

    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query->whereNull('deleted_at');
    }

    public function scopeOfType(Builder $query, MessageType|string $type): Builder
    {
        $value = $type instanceof MessageType ? $type->value : $type;

        return $query->where('type', $value);
    }

    public function scopeVisibleTo(Builder $query, Model $actor): Builder
    {
        return $query->where(function (Builder $q) use ($actor) {
            $q->whereNull('deleted_at')
                ->orWhere(function (Builder $subQ) use ($actor) {
                    $subQ->whereDoesntHave('deletions', function (Builder $delQ) use ($actor) {
                        $delQ->where('actor_type', $actor->getMorphClass())
                            ->where('actor_id', $actor->getKey());
                    });
                });
        });
    }

    // Accessors

    public function getTypeEnumAttribute(): MessageType
    {
        return MessageType::from($this->type);
    }

    public function getCurrentPayloadAttribute(): array
    {
        if (! config('chat-engine.messages.immutable', true)) {
            return $this->payload;
        }

        $latestVersion = $this->versions()->latest('created_at')->first();

        return $latestVersion ? $latestVersion->payload : $this->payload;
    }

    public function getIsEditedAttribute(): bool
    {
        if (! config('chat-engine.messages.immutable', true)) {
            return false;
        }

        return $this->versions()->exists();
    }

    public function getIsDeletedAttribute(): bool
    {
        return $this->deleted_at !== null;
    }

    // Methods

    public function edit(array $newPayload, Model $editedBy): MessageVersion|self
    {
        if (config('chat-engine.messages.immutable', true)) {
            $version = $this->versions()->create([
                'payload' => $newPayload,
                'edited_by_type' => $editedBy->getMorphClass(),
                'edited_by_id' => $editedBy->getKey(),
                'created_at' => now(),
            ]);

            MessageEdited::dispatch($this, $editedBy, $version);

            return $version;
        }

        $this->payload = $newPayload;
        $this->save();

        MessageEdited::dispatch($this, $editedBy);

        return $this;
    }

    public function deleteFor(Model $actor): MessageDeletion
    {
        $deletion = $this->deletions()->firstOrCreate([
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
        ], [
            'deleted_at' => now(),
        ]);

        MessageDeletedForActor::dispatch($this, $actor, $deletion);

        return $deletion;
    }

    public function restoreFor(Model $actor): bool
    {
        $deleted = $this->deletions()
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey())
            ->delete() > 0;

        if ($deleted) {
            MessageRestored::dispatch($this, $actor);
        }

        return $deleted;
    }

    public function deleteGlobally(Model $deletedBy): bool
    {
        $this->deleted_at = now();
        $this->deleted_by_type = $deletedBy->getMorphClass();
        $this->deleted_by_id = $deletedBy->getKey();

        $result = $this->save();

        if ($result) {
            MessageDeletedGlobally::dispatch($this, $deletedBy);
        }

        return $result;
    }

    public function isDeletedFor(Model $actor): bool
    {
        if ($this->deleted_at !== null) {
            return true;
        }

        return $this->deletions()
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey())
            ->exists();
    }

    public function isReadBy(Model $actor): bool
    {
        return $this->deliveries()
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey())
            ->whereNotNull('read_at')
            ->exists();
    }

    public function isDeliveredTo(Model $actor): bool
    {
        return $this->deliveries()
            ->where('actor_type', $actor->getMorphClass())
            ->where('actor_id', $actor->getKey())
            ->whereNotNull('delivered_at')
            ->exists();
    }

    public function markAsDeliveredTo(Model $actor): MessageDelivery
    {
        $delivery = $this->deliveries()->updateOrCreate([
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
        ], [
            'delivered_at' => now(),
        ]);

        MessageDelivered::dispatch($this, $actor, $delivery);

        return $delivery;
    }

    public function markAsReadBy(Model $actor): MessageDelivery
    {
        $delivery = $this->deliveries()->updateOrCreate([
            'actor_type' => $actor->getMorphClass(),
            'actor_id' => $actor->getKey(),
        ], [
            'delivered_at' => $this->deliveries()
                ->where('actor_type', $actor->getMorphClass())
                ->where('actor_id', $actor->getKey())
                ->value('delivered_at') ?? now(),
            'read_at' => now(),
        ]);

        MessageRead::dispatch($this, $actor, $delivery);

        return $delivery;
    }

    public function isSentBy(Model $actor): bool
    {
        return $this->sender_type === $actor->getMorphClass()
            && $this->sender_id === $actor->getKey();
    }
}
