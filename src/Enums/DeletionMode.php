<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Enums;

enum DeletionMode: string
{
    case SOFT = 'soft';
    case HARD = 'hard';
    case HYBRID = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::SOFT => 'Soft Delete',
            self::HARD => 'Hard Delete',
            self::HYBRID => 'Hybrid',
        };
    }

    public function allowsSoftDelete(): bool
    {
        return in_array($this, [self::SOFT, self::HYBRID], true);
    }

    public function allowsHardDelete(): bool
    {
        return in_array($this, [self::HARD, self::HYBRID], true);
    }
}
