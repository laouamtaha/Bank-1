<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Ritechoice23\ChatEngine\Models\Message
 */
class MessageResource extends JsonResource
{
    /**
     * The actor viewing the message (for visibility/read status).
     */
    protected static ?Model $viewingActor = null;

    /**
     * Set the actor viewing the messages.
     */
    public static function viewAs(?Model $actor): void
    {
        static::$viewingActor = $actor;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var \Ritechoice23\ChatEngine\Models\Message $message */
        $message = $this->resource;
        $viewingActor = static::$viewingActor;

        return [
            'id' => $message->id,
            'thread_id' => $message->thread_id,
            'type' => $message->type,
            'payload' => $message->payload,
            'encrypted' => $message->encrypted,
            'created_at' => $message->created_at->toISOString(),

            // Sender (polymorphic)
            'sender' => [
                'id' => $message->sender_id,
                'type' => $message->sender_type,
                'name' => $this->whenLoaded('sender', fn () => $message->sender->name ?? null),
            ],

            // Author (if different from sender)
            'author' => $this->when($message->author_id !== null, fn () => [
                'id' => $message->author_id,
                'type' => $message->author_type,
                'name' => $this->whenLoaded('author', fn () => $message->author->name ?? null),
            ]),

            // Edit information (only when versions are loaded)
            'is_edited' => $this->relationLoaded('versions') ? $message->is_edited : false,
            'last_version' => $this->when(
                $this->relationLoaded('versions') && $message->versions->isNotEmpty(),
                fn () => new MessageVersionResource($message->versions->last())
            ),
            'versions_count' => $this->whenCounted('versions'),

            // Deletion status
            'deleted_at' => $message->deleted_at?->toISOString(),
            'is_deleted' => $message->deleted_at !== null,

            // Delivery status for the viewing actor
            'delivery' => $this->when($viewingActor !== null, fn () => [
                'is_delivered' => $message->isDeliveredTo($viewingActor),
                'is_read' => $message->isReadBy($viewingActor),
            ]),

            // Reactions (from laravel-reactions package)
            'reactions' => [
                'count' => $message->reactionsCount(),
                'breakdown' => $message->reactionsBreakdown(),
                'has_reacted' => $viewingActor !== null
                    ? $message->isReactedBy($viewingActor)
                    : null,
                'user_reaction' => $viewingActor !== null
                    ? $message->reactionBy($viewingActor)?->getAttribute('reaction_type')
                    : null,
            ],

            // Thread relationship
            'thread' => new ThreadResource($this->whenLoaded('thread')),

            // Deliveries (for admins/detailed views)
            'deliveries' => MessageDeliveryResource::collection($this->whenLoaded('deliveries')),

            // Attachments
            'attachments' => MessageAttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
