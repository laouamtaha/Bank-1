<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Ritechoice23\ChatEngine\Models\ThreadParticipant
 */
class ThreadParticipantResource extends JsonResource
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
            'role' => $this->role,
            'is_active' => $this->isActive(),
            'joined_at' => $this->joined_at->toISOString(),
            'left_at' => $this->left_at?->toISOString(),

            // Polymorphic actor (resolved if loaded)
            'actor' => $this->when($this->relationLoaded('actor'), function () {
                return [
                    'id' => $this->actor->getKey(),
                    'type' => $this->actor_type,
                    'name' => $this->actor->name ?? null,
                ];
            }),

            // Thread relationship
            'thread' => new ThreadResource($this->whenLoaded('thread')),
        ];
    }
}
