<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Enums;

enum MessageType: string
{
    case TEXT = 'text';
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case FILE = 'file';
    case LOCATION = 'location';
    case CONTACT = 'contact';
    case SYSTEM = 'system';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text',
            self::IMAGE => 'Image',
            self::VIDEO => 'Video',
            self::AUDIO => 'Audio',
            self::FILE => 'File',
            self::LOCATION => 'Location',
            self::CONTACT => 'Contact',
            self::SYSTEM => 'System',
            self::CUSTOM => 'Custom',
        };
    }

    public function isMedia(): bool
    {
        return in_array($this, [self::IMAGE, self::VIDEO, self::AUDIO, self::FILE], true);
    }

    public function requiresUrl(): bool
    {
        return in_array($this, [self::IMAGE, self::VIDEO, self::AUDIO, self::FILE], true);
    }
}
