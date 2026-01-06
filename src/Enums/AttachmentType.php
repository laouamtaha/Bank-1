<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Enums;

enum AttachmentType: string
{
    case IMAGE = 'image';
    case VIDEO = 'video';
    case AUDIO = 'audio';
    case FILE = 'file';

    public function label(): string
    {
        return match ($this) {
            self::IMAGE => 'Image',
            self::VIDEO => 'Video',
            self::AUDIO => 'Audio',
            self::FILE => 'File',
        };
    }

    /**
     * Check if this attachment type supports duration.
     */
    public function hasDuration(): bool
    {
        return in_array($this, [self::VIDEO, self::AUDIO], true);
    }

    /**
     * Check if this attachment type supports dimensions.
     */
    public function hasDimensions(): bool
    {
        return in_array($this, [self::IMAGE, self::VIDEO], true);
    }

    /**
     * Check if this attachment type supports BlurHash.
     */
    public function supportsBlurHash(): bool
    {
        return in_array($this, [self::IMAGE, self::VIDEO], true);
    }

    /**
     * Get allowed MIME type prefixes for this attachment type.
     */
    public function mimeTypePrefixes(): array
    {
        return match ($this) {
            self::IMAGE => ['image/'],
            self::VIDEO => ['video/'],
            self::AUDIO => ['audio/'],
            self::FILE => [], // Any MIME type
        };
    }
}
