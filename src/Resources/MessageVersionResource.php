<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Ritechoice23\ChatEngine\Models\MessageVersion
 */
class MessageVersionResource extends JsonResource
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
            'message_id' => $this->message_id,
            'payload' => $this->payload,
            'created_at' => $this->created_at->toISOString(),

            // Editor (polymorphic)
            'edited_by' => [
                'id' => $this->edited_by_id,
                'type' => $this->edited_by_type,
            ],

            // Parent message
            'message' => new MessageResource($this->whenLoaded('message')),
        ];
    }
}
