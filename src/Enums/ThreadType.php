<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Enums;

enum ThreadType: string
{
    case DIRECT = 'direct';
    case GROUP = 'group';
    case CHANNEL = 'channel';
    case BROADCAST = 'broadcast';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::DIRECT => 'Direct Message',
            self::GROUP => 'Group',
            self::CHANNEL => 'Channel',
            self::BROADCAST => 'Broadcast',
            self::CUSTOM => 'Custom',
        };
    }

    public function allowsMultipleParticipants(): bool
    {
        return $this !== self::DIRECT;
    }

    public function requiresExactTwoParticipants(): bool
    {
        return $this === self::DIRECT;
    }
}
