<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Ritechoice23\ChatEngine\Models\MessageAttachment;

/**
 * @mixin MessageAttachment
 */
class MessageAttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // For view_once attachments, hide URL if already consumed
        $url = $this->view_once && $this->viewed_at
            ? null
            : $this->url;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'url' => $url,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'human_size' => $this->human_size,
            'duration' => $this->duration,
            'human_duration' => $this->human_duration,
            'width' => $this->width,
            'height' => $this->height,
            'thumbnail_url' => $this->thumbnail_url,
            'blurhash' => $this->blurhash,
            'caption' => $this->caption,
            'view_once' => $this->view_once,
            'is_consumed' => $this->is_consumed,
            'metadata' => $this->metadata,
            'order' => $this->order,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
