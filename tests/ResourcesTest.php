<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Actions\CreateThread;
use Ritechoice23\ChatEngine\Actions\SendMessage;
use Ritechoice23\ChatEngine\Enums\ParticipantRole;
use Ritechoice23\ChatEngine\Enums\ThreadType;
use Ritechoice23\ChatEngine\Resources\MessageCollection;
use Ritechoice23\ChatEngine\Resources\MessageDeliveryResource;
use Ritechoice23\ChatEngine\Resources\MessageResource;
use Ritechoice23\ChatEngine\Resources\MessageVersionResource;
use Ritechoice23\ChatEngine\Resources\ThreadCollection;
use Ritechoice23\ChatEngine\Resources\ThreadParticipantResource;
use Ritechoice23\ChatEngine\Resources\ThreadResource;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);

    $createThread = new CreateThread;
    $this->thread = $createThread->direct($this->userA, $this->userB);

    $sendMessage = new SendMessage;
    $this->message = $sendMessage->text($this->thread, $this->userA, 'Test message');
});

describe('ThreadResource', function () {
    it('transforms thread to array', function () {
        $resource = new ThreadResource($this->thread);
        $array = $resource->resolve(request());

        expect($array)
            ->toHaveKey('id', $this->thread->id)
            ->toHaveKey('type', ThreadType::DIRECT->value)
            ->toHaveKey('created_at')
            ->toHaveKey('is_locked', false);
    });

    it('includes participants when loaded', function () {
        $thread = $this->thread->load('participants');
        $resource = new ThreadResource($thread);
        $array = $resource->resolve(request());

        expect($array)->toHaveKey('participants')
            ->and($array['participants'])->toHaveCount(2);
    });

    it('includes messages when loaded', function () {
        $thread = $this->thread->load('messages');
        $resource = new ThreadResource($thread);
        $array = $resource->resolve(request());

        expect($array)->toHaveKey('messages')
            ->and($array['messages'])->toHaveCount(1);
    });
});

describe('ThreadParticipantResource', function () {
    it('transforms participant to array', function () {
        $participant = $this->thread->participants()->first();
        $resource = new ThreadParticipantResource($participant);
        $array = $resource->resolve(request());

        expect($array)
            ->toHaveKey('id')
            ->toHaveKey('role')
            ->toHaveKey('is_active', true)
            ->toHaveKey('joined_at');
    });

    it('includes actor info when loaded', function () {
        $participant = $this->thread->participants()->with('actor')->first();
        $resource = new ThreadParticipantResource($participant);
        $array = $resource->resolve(request());

        expect($array)->toHaveKey('actor')
            ->and($array['actor'])->toHaveKey('id')
            ->and($array['actor'])->toHaveKey('type');
    });
});

describe('MessageResource', function () {
    it('transforms message to array', function () {
        $resource = new MessageResource($this->message);
        $array = $resource->resolve(request());

        expect($array)
            ->toHaveKey('id', $this->message->id)
            ->toHaveKey('thread_id', $this->thread->id)
            ->toHaveKey('type', 'text')
            ->toHaveKey('payload')
            ->toHaveKey('sender')
            ->toHaveKey('is_edited', false)
            ->toHaveKey('is_deleted', false);
    });

    it('shows delivery status for viewing actor', function () {
        MessageResource::viewAs($this->userB);

        $resource = new MessageResource($this->message);
        $array = $resource->resolve(request());

        expect($array)->toHaveKey('delivery')
            ->and($array['delivery']['is_delivered'])->toBeFalse()
            ->and($array['delivery']['is_read'])->toBeFalse();

        // Clean up static state
        MessageResource::viewAs(null);
    });

    it('shows read status correctly', function () {
        $this->message->markAsReadBy($this->userB);
        MessageResource::viewAs($this->userB);

        $resource = new MessageResource($this->message);
        $array = $resource->resolve(request());

        expect($array['delivery']['is_read'])->toBeTrue();

        MessageResource::viewAs(null);
    });
});

describe('MessageVersionResource', function () {
    it('transforms version to array', function () {
        config()->set('chat-engine.messages.immutable', true);
        $version = $this->message->edit(['content' => 'Edited'], $this->userA);

        $resource = new MessageVersionResource($version);
        $array = $resource->resolve(request());

        expect($array)
            ->toHaveKey('id')
            ->toHaveKey('message_id', $this->message->id)
            ->toHaveKey('payload')
            ->toHaveKey('edited_by')
            ->toHaveKey('created_at');
    });
});

describe('MessageDeliveryResource', function () {
    it('transforms delivery to array', function () {
        $delivery = $this->message->markAsDeliveredTo($this->userB);

        $resource = new MessageDeliveryResource($delivery);
        $array = $resource->resolve(request());

        expect($array)
            ->toHaveKey('message_id', $this->message->id)
            ->toHaveKey('actor')
            ->toHaveKey('delivered_at')
            ->toHaveKey('is_delivered', true)
            ->toHaveKey('is_read', false);
    });
});

describe('ThreadCollection', function () {
    it('wraps threads in data key', function () {
        $createThread = new CreateThread;
        $thread2 = $createThread->execute(
            type: ThreadType::GROUP,
            participants: [
                ['actor' => $this->userA, 'role' => ParticipantRole::OWNER],
            ],
            name: 'Group'
        );

        $threads = collect([$this->thread, $thread2]);
        $collection = new ThreadCollection($threads);
        $array = $collection->resolve(request());

        expect($array)
            ->toHaveKey('data')
            ->and($array['data'])->toHaveCount(2);
    });
});

describe('MessageCollection', function () {
    it('wraps messages in data key', function () {
        $sendMessage = new SendMessage;
        $message2 = $sendMessage->text($this->thread, $this->userB, 'Reply');

        $messages = collect([$this->message, $message2]);
        $collection = new MessageCollection($messages);
        $array = $collection->resolve(request());

        expect($array)
            ->toHaveKey('data')
            ->and($array['data'])->toHaveCount(2);
    });
});
