<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Actions;

use Illuminate\Http\UploadedFile;
use Ritechoice23\ChatEngine\Enums\AttachmentType;

class UploadAttachment
{
    /**
     * Upload a file and return attachment data ready for MessageBuilder.
     *
     * @return array{
     *     path: string,
     *     disk: string,
     *     filename: string,
     *     mime_type: string|null,
     *     size: int,
     *     type: string
     * }
     */
    public function execute(
        UploadedFile $file,
        ?AttachmentType $type = null,
        ?string $disk = null,
        ?string $path = null,
        ?string $visibility = null
    ): array {
        $disk = $disk ?? config('chat-engine.attachments.disk', 'public');
        $path = $path ?? config('chat-engine.attachments.path', 'chat-attachments');
        $visibility = $visibility ?? config('chat-engine.attachments.visibility', 'public');

        // Auto-detect attachment type from MIME type
        $type = $type ?? $this->detectType($file->getMimeType());

        // Store the file
        $storedPath = $file->store($path, [
            'disk' => $disk,
            'visibility' => $visibility,
        ]);

        return [
            'path' => $storedPath,
            'disk' => $disk,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'type' => $type->value,
        ];
    }

    /**
     * Upload multiple files and return attachment data array.
     *
     * @param  array<UploadedFile>  $files
     * @return array<array{path: string, disk: string, filename: string, mime_type: string|null, size: int, type: string}>
     */
    public function executeMany(
        array $files,
        ?string $disk = null,
        ?string $path = null
    ): array {
        return array_map(
            fn (UploadedFile $file) => $this->execute($file, null, $disk, $path),
            $files
        );
    }

    /**
     * Detect attachment type from MIME type.
     */
    protected function detectType(?string $mimeType): AttachmentType
    {
        if (! $mimeType) {
            return AttachmentType::FILE;
        }

        return match (true) {
            str_starts_with($mimeType, 'image/') => AttachmentType::IMAGE,
            str_starts_with($mimeType, 'video/') => AttachmentType::VIDEO,
            str_starts_with($mimeType, 'audio/') => AttachmentType::AUDIO,
            default => AttachmentType::FILE,
        };
    }
}
