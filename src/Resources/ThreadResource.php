<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Ritechoice23\ChatEngine\Models\Thread
 */
class ThreadResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'metadata' => $this->metadata,
            'is_locked' => $this->is_locked,
            'permissions' => $this->permissions,
            'created_at' => $this->created_at->toISOString(),

            // Relationships
            'participants' => ThreadParticipantResource::collection($this->whenLoaded('participants')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'latest_message' => new MessageResource($this->whenLoaded('latestMessage')),

            // Counts
            'participants_count' => $this->whenCounted('participants'),
            'messages_count' => $this->whenCounted('messages'),

            // Computed (when available via select/addSelect)
            'unread_count' => $this->when(
                $this->resource->getAttributes()['unread_count'] ?? false,
                fn () => $this->resource->getAttribute('unread_count')
            ),
        ];
    }
}
