<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Ritechoice23\ChatEngine\Models\MessageDelivery
 */
class MessageDeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message_id' => $this->message_id,

            // Actor (polymorphic)
            'actor' => [
                'id' => $this->actor_id,
                'type' => $this->actor_type,
            ],

            // Timestamps
            'delivered_at' => $this->delivered_at?->toISOString(),
            'read_at' => $this->read_at?->toISOString(),

            // Status helpers
            'is_delivered' => $this->isDelivered(),
            'is_read' => $this->isRead(),
        ];
    }
}
