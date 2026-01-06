<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Hash;
use Ritechoice23\ChatEngine\Enums\ParticipantRole;

/**
 * @property int $id
 * @property int $thread_id
 * @property string $actor_type
 * @property int $actor_id
 * @property string $role
 * @property \Carbon\Carbon $joined_at
 * @property \Carbon\Carbon|null $left_at
 * @property string|null $chat_lock_pin
 * @property string|null $public_key
 * @property string|null $security_code
 */
class ThreadParticipant extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'thread_id',
        'actor_type',
        'actor_id',
        'role',
        'joined_at',
        'left_at',
        'chat_lock_pin',
        'public_key',
        'security_code',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function getTable(): string
    {
        return config('chat-engine.tables.thread_participants', 'thread_participants');
    }

    protected static function booted(): void
    {
        static::creating(function (ThreadParticipant $participant) {
            $participant->role = $participant->role ?? ParticipantRole::MEMBER->value;
            $participant->joined_at = $participant->joined_at ?? now();
        });
    }

    // Relationships

    public function thread(): BelongsTo
    {
        return $this->belongsTo(
            config('chat-engine.models.thread', Thread::class),
            'thread_id'
        );
    }

    public function actor(): MorphTo
    {
        return $this->morphTo('actor');
    }

    // Accessors

    public function getRoleEnumAttribute(): ParticipantRole
    {
        return ParticipantRole::from($this->role);
    }

    // Methods

    public function isActive(): bool
    {
        return $this->left_at === null;
    }

    public function leave(): bool
    {
        $this->left_at = now();

        return $this->save();
    }

    public function rejoin(): bool
    {
        $this->left_at = null;
        $this->joined_at = now();

        return $this->save();
    }

    public function hasRole(ParticipantRole $role): bool
    {
        return $this->role === $role->value;
    }

    public function canManageParticipants(): bool
    {
        return $this->getRoleEnumAttribute()->canManageParticipants();
    }

    public function canDeleteThread(): bool
    {
        return $this->getRoleEnumAttribute()->canDeleteThread();
    }

    // Chat Lock PIN Methods

    /**
     * Lock this chat with a PIN (personal lock).
     */
    public function lockChat(string $pin): bool
    {
        $this->chat_lock_pin = Hash::make($pin);

        return $this->save();
    }

    /**
     * Remove the chat lock.
     */
    public function unlockChat(): bool
    {
        $this->chat_lock_pin = null;

        return $this->save();
    }

    /**
     * Check if this chat is locked.
     */
    public function isChatLocked(): bool
    {
        return $this->chat_lock_pin !== null;
    }

    /**
     * Verify the PIN against the stored hash.
     */
    public function checkPin(string $pin): bool
    {
        if (! $this->chat_lock_pin) {
            return true; // Not locked
        }

        return Hash::check($pin, $this->chat_lock_pin);
    }

    // E2E Security Methods

    /**
     * Set the public key for E2E encryption verification.
     */
    public function setPublicKey(string $publicKey): bool
    {
        $this->public_key = $publicKey;
        $this->security_code = $this->generateSecurityCode($publicKey);

        return $this->save();
    }

    /**
     * Generate a security verification code from a public key.
     */
    protected function generateSecurityCode(string $publicKey): string
    {
        // Generate a 60-digit numeric code (like WhatsApp)
        $hash = hash('sha256', $publicKey);
        $code = '';

        for ($i = 0; $i < 60; $i++) {
            $code .= hexdec(substr($hash, $i % 64, 1)) % 10;
        }

        return $code;
    }

    /**
     * Get the security code formatted for display (12 groups of 5 digits).
     */
    public function getFormattedSecurityCodeAttribute(): ?string
    {
        if (! $this->security_code) {
            return null;
        }

        return implode(' ', str_split($this->security_code, 5));
    }

    /**
     * Verify security codes match with another participant.
     */
    public function verifySecurityWith(ThreadParticipant $other): bool
    {
        if (! $this->public_key || ! $other->public_key) {
            return false;
        }

        // For E2E verification, both parties should be able to compute the same code
        // by combining their public keys in a deterministic order
        $combinedKey = $this->public_key < $other->public_key
            ? $this->public_key.$other->public_key
            : $other->public_key.$this->public_key;

        $sharedCode = $this->generateSecurityCode($combinedKey);

        return $this->security_code === $sharedCode || $other->security_code === $sharedCode;
    }
}
