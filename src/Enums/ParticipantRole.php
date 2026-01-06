<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Enums;

enum ParticipantRole: string
{
    case MEMBER = 'member';
    case ADMIN = 'admin';
    case OWNER = 'owner';

    public function label(): string
    {
        return match ($this) {
            self::MEMBER => 'Member',
            self::ADMIN => 'Admin',
            self::OWNER => 'Owner',
        };
    }

    public function canManageParticipants(): bool
    {
        return match ($this) {
            self::MEMBER => false,
            self::ADMIN, self::OWNER => true,
        };
    }

    public function canDeleteThread(): bool
    {
        return $this === self::OWNER;
    }
}
