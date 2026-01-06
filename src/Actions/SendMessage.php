<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Actions;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Enums\MessageType;
use Ritechoice23\ChatEngine\Events\MessageSent;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Models\Thread;
use Ritechoice23\ChatEngine\Policies\ThreadPolicy;

class SendMessage
{
    /**
     * Send a message to a thread.
     */
    public function execute(
        Thread|int $thread,
        Model $sender,
        array $payload,
        MessageType $type = MessageType::TEXT,
        ?Model $author = null,
        bool $encrypted = false,
        ?string $encryptionDriver = null,
    ): Message {
        $threadId = $thread instanceof Thread ? $thread->id : $thread;
        $threadInstance = $thread instanceof Thread
            ? $thread
            : Thread::find($threadId);

        // Check if actor can send messages in this thread
        $policy = new ThreadPolicy;
        if (! $policy->sendMessage($sender, $threadInstance)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Not authorized to send messages in this thread.'
            );
        }

        $messageModel = config('chat-engine.models.message', Message::class);

        $message = $messageModel::create([
            'thread_id' => $threadId,
            'sender_type' => $sender->getMorphClass(),
            'sender_id' => $sender->getKey(),
            'author_type' => $author?->getMorphClass(),
            'author_id' => $author?->getKey(),
            'type' => $type->value,
            'payload' => $payload,
            'encrypted' => $encrypted,
            'encryption_driver' => $encryptionDriver,
        ]);

        // Auto-mark as read by sender
        $message->markAsReadBy($sender);

        MessageSent::dispatch($message, $threadInstance, $sender);

        return $message;
    }

    /**
     * Send a text message.
     */
    public function text(Thread|int $thread, Model $sender, string $content): Message
    {
        return $this->execute(
            thread: $thread,
            sender: $sender,
            payload: ['type' => MessageType::TEXT->value, 'content' => $content],
            type: MessageType::TEXT,
        );
    }

    /**
     * Send an image message.
     */
    public function image(
        Thread|int $thread,
        Model $sender,
        string $url,
        ?string $caption = null
    ): Message {
        return $this->execute(
            thread: $thread,
            sender: $sender,
            payload: array_filter([
                'type' => MessageType::IMAGE->value,
                'url' => $url,
                'caption' => $caption,
            ], fn ($v) => $v !== null),
            type: MessageType::IMAGE,
        );
    }

    /**
     * Send a system message.
     */
    public function system(
        Thread|int $thread,
        Model $sender,
        string $content,
        ?string $action = null
    ): Message {
        return $this->execute(
            thread: $thread,
            sender: $sender,
            payload: array_filter([
                'type' => MessageType::SYSTEM->value,
                'content' => $content,
                'action' => $action,
            ], fn ($v) => $v !== null),
            type: MessageType::SYSTEM,
        );
    }
}
