<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Actions;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Events\MessageEdited;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Models\MessageVersion;
use Ritechoice23\ChatEngine\Policies\MessagePolicy;

class EditMessage
{
    /**
     * Edit a message's payload.
     *
     * In immutable mode, creates a new version.
     * In mutable mode, updates the payload directly.
     */
    public function execute(
        Message $message,
        array $newPayload,
        Model $editedBy,
    ): MessageVersion|Message {
        // Check policy authorization
        $policy = new MessagePolicy;
        if (! $policy->edit($editedBy, $message)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                'Not authorized to edit this message.'
            );
        }

        $this->validateEditPermission($message, $editedBy);
        $this->validateEditTimeLimit($message);

        if (config('chat-engine.messages.immutable', true)) {
            $version = $message->versions()->create([
                'payload' => $newPayload,
                'edited_by_type' => $editedBy->getMorphClass(),
                'edited_by_id' => $editedBy->getKey(),
                'created_at' => now(),
            ]);

            MessageEdited::dispatch($message, $editedBy, $version);

            return $version;
        }

        $message->payload = $newPayload;
        $message->save();

        MessageEdited::dispatch($message, $editedBy);

        return $message;
    }

    protected function validateEditPermission(Message $message, Model $editedBy): void
    {
        // By default, only the sender can edit their own messages
        if (! $message->isSentBy($editedBy)) {
            throw new \InvalidArgumentException('Only the message sender can edit the message.');
        }
    }

    protected function validateEditTimeLimit(Message $message): void
    {
        $timeLimit = config('chat-engine.messages.edit_time_limit');

        if ($timeLimit === null) {
            return;
        }

        $minutesSinceSent = $message->created_at->diffInMinutes(now());

        if ($minutesSinceSent > $timeLimit) {
            throw new \InvalidArgumentException(
                "Messages can only be edited within {$timeLimit} minutes of sending."
            );
        }
    }
}
