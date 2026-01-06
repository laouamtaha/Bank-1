<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Actions\CreateThread;
use Ritechoice23\ChatEngine\Actions\DeleteMessage;
use Ritechoice23\ChatEngine\Actions\EditMessage;
use Ritechoice23\ChatEngine\Actions\ManageParticipants;
use Ritechoice23\ChatEngine\Actions\MarkDelivery;
use Ritechoice23\ChatEngine\Actions\SendMessage;
use Ritechoice23\ChatEngine\Enums\MessageType;
use Ritechoice23\ChatEngine\Enums\ParticipantRole;
use Ritechoice23\ChatEngine\Enums\ThreadType;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);
    $this->userC = User::create(['name' => 'User C', 'email' => 'c@test.com']);
});

describe('CreateThread Action', function () {
    it('can create a direct thread', function () {
        $action = new CreateThread;

        $thread = $action->direct($this->userA, $this->userB);

        expect($thread->type)->toBe(ThreadType::DIRECT->value)
            ->and($thread->participants)->toHaveCount(2);
    });

    it('can create a group thread', function () {
        $action = new CreateThread;

        $thread = $action->execute(
            type: ThreadType::GROUP,
            participants: [
                ['actor' => $this->userA, 'role' => ParticipantRole::OWNER],
                ['actor' => $this->userB, 'role' => ParticipantRole::MEMBER],
                ['actor' => $this->userC, 'role' => ParticipantRole::MEMBER],
            ],
            name: 'Test Group'
        );

        expect($thread->type)->toBe(ThreadType::GROUP->value)
            ->and($thread->name)->toBe('Test Group')
            ->and($thread->participants)->toHaveCount(3);
    });

    it('finds existing thread instead of creating duplicate', function () {
        $action = new CreateThread;

        $thread1 = $action->direct($this->userA, $this->userB);
        $thread2 = $action->direct($this->userA, $this->userB);

        expect($thread1->id)->toBe($thread2->id);
    });

    it('throws exception for direct thread with wrong participant count', function () {
        $action = new CreateThread;

        $action->execute(
            type: ThreadType::DIRECT,
            participants: [['actor' => $this->userA, 'role' => ParticipantRole::MEMBER]]
        );
    })->throws(InvalidArgumentException::class);
});

describe('SendMessage Action', function () {
    beforeEach(function () {
        $action = new CreateThread;
        $this->thread = $action->direct($this->userA, $this->userB);
    });

    it('can send a text message', function () {
        $action = new SendMessage;

        $message = $action->text($this->thread, $this->userA, 'Hello World');

        expect($message->type)->toBe(MessageType::TEXT->value)
            ->and($message->payload['content'])->toBe('Hello World')
            ->and($message->sender_id)->toBe($this->userA->id);
    });

    it('can send an image message', function () {
        $action = new SendMessage;

        $message = $action->image($this->thread, $this->userA, 'https://example.com/image.jpg', 'Caption');

        expect($message->type)->toBe(MessageType::IMAGE->value)
            ->and($message->payload['url'])->toBe('https://example.com/image.jpg')
            ->and($message->payload['caption'])->toBe('Caption');
    });

    it('auto-marks message as read by sender', function () {
        $action = new SendMessage;

        $message = $action->text($this->thread, $this->userA, 'Hello');

        expect($message->isReadBy($this->userA))->toBeTrue();
    });
});

describe('EditMessage Action', function () {
    beforeEach(function () {
        $createThread = new CreateThread;
        $this->thread = $createThread->direct($this->userA, $this->userB);

        $sendMessage = new SendMessage;
        $this->message = $sendMessage->text($this->thread, $this->userA, 'Original');
    });

    it('can edit message in immutable mode', function () {
        config()->set('chat-engine.messages.immutable', true);
        $action = new EditMessage;

        $result = $action->execute(
            $this->message,
            ['type' => 'text', 'content' => 'Edited'],
            $this->userA
        );

        expect($result)->toBeInstanceOf(\Ritechoice23\ChatEngine\Models\MessageVersion::class)
            ->and($result->payload['content'])->toBe('Edited')
            ->and($this->message->versions()->count())->toBe(1);
    });

    it('can edit message in mutable mode', function () {
        config()->set('chat-engine.messages.immutable', false);
        $action = new EditMessage;

        $result = $action->execute(
            $this->message,
            ['type' => 'text', 'content' => 'Edited'],
            $this->userA
        );

        expect($result)->toBeInstanceOf(\Ritechoice23\ChatEngine\Models\Message::class)
            ->and($result->payload['content'])->toBe('Edited')
            ->and($this->message->versions()->count())->toBe(0);
    });

    it('throws exception when non-sender tries to edit', function () {
        $action = new EditMessage;

        $action->execute(
            $this->message,
            ['type' => 'text', 'content' => 'Edited'],
            $this->userB
        );
    })->throws(\Illuminate\Auth\Access\AuthorizationException::class);
});

describe('DeleteMessage Action', function () {
    beforeEach(function () {
        $createThread = new CreateThread;
        $this->thread = $createThread->direct($this->userA, $this->userB);

        $sendMessage = new SendMessage;
        $this->message = $sendMessage->text($this->thread, $this->userA, 'Test');
    });

    it('can delete message for specific actor', function () {
        $action = new DeleteMessage;

        $deletion = $action->forActor($this->message, $this->userB);

        expect($deletion)->toBeInstanceOf(\Ritechoice23\ChatEngine\Models\MessageDeletion::class)
            ->and($this->message->isDeletedFor($this->userB))->toBeTrue()
            ->and($this->message->isDeletedFor($this->userA))->toBeFalse();
    });

    it('can delete message globally', function () {
        config()->set('chat-engine.messages.deletion_mode', 'soft');
        $action = new DeleteMessage;

        $result = $action->globally($this->message, $this->userA);

        expect($result)->toBeTrue()
            ->and($this->message->fresh()->deleted_at)->not->toBeNull();
    });

    it('can restore message for actor', function () {
        $action = new DeleteMessage;

        $action->forActor($this->message, $this->userB);
        $restored = $action->restoreForActor($this->message, $this->userB);

        expect($restored)->toBeTrue()
            ->and($this->message->isDeletedFor($this->userB))->toBeFalse();
    });
});

describe('MarkDelivery Action', function () {
    beforeEach(function () {
        $createThread = new CreateThread;
        $this->thread = $createThread->direct($this->userA, $this->userB);

        $sendMessage = new SendMessage;
        $this->message = $sendMessage->text($this->thread, $this->userA, 'Test');
    });

    it('can mark message as delivered', function () {
        $action = new MarkDelivery;

        $delivery = $action->asDelivered($this->message, $this->userB);

        expect($delivery->isDelivered())->toBeTrue()
            ->and($this->message->isDeliveredTo($this->userB))->toBeTrue();
    });

    it('can mark message as read', function () {
        $action = new MarkDelivery;

        $delivery = $action->asRead($this->message, $this->userB);

        expect($delivery->isRead())->toBeTrue()
            ->and($delivery->isDelivered())->toBeTrue()
            ->and($this->message->isReadBy($this->userB))->toBeTrue();
    });

    it('can mark entire thread as read', function () {
        $sendMessage = new SendMessage;
        $sendMessage->text($this->thread, $this->userA, 'Message 1');
        $sendMessage->text($this->thread, $this->userA, 'Message 2');
        $sendMessage->text($this->thread, $this->userA, 'Message 3');

        $action = new MarkDelivery;
        $count = $action->markThreadAsRead($this->thread, $this->userB);

        // 4 messages total (1 from beforeEach + 3 new)
        expect($count)->toBe(4);
    });
});

describe('ManageParticipants Action', function () {
    beforeEach(function () {
        $createThread = new CreateThread;
        $this->thread = $createThread->execute(
            type: ThreadType::GROUP,
            participants: [
                ['actor' => $this->userA, 'role' => ParticipantRole::OWNER],
            ],
            name: 'Test Group'
        );
    });

    it('can add participant to thread', function () {
        $action = new ManageParticipants;

        $participant = $action->add($this->thread, $this->userB, ParticipantRole::MEMBER);

        expect($participant->actor_id)->toBe($this->userB->id)
            ->and($participant->role)->toBe(ParticipantRole::MEMBER->value)
            ->and($this->thread->hasParticipant($this->userB))->toBeTrue();
    });

    it('can remove participant from thread', function () {
        $action = new ManageParticipants;

        $action->add($this->thread, $this->userB);
        $removed = $action->remove($this->thread, $this->userB);

        expect($removed)->toBeTrue()
            ->and($this->thread->hasParticipant($this->userB))->toBeFalse();
    });

    it('can update participant role', function () {
        $action = new ManageParticipants;

        $action->add($this->thread, $this->userB, ParticipantRole::MEMBER);
        $updated = $action->updateRole($this->thread, $this->userB, ParticipantRole::ADMIN);

        expect($updated)->not->toBeNull()
            ->and($updated->role)->toBe(ParticipantRole::ADMIN->value);
    });

    it('can transfer ownership', function () {
        $action = new ManageParticipants;

        $action->add($this->thread, $this->userB, ParticipantRole::ADMIN);
        $transferred = $action->transferOwnership($this->thread, $this->userA, $this->userB);

        expect($transferred)->toBeTrue();

        $participantA = $this->thread->fresh()->getParticipant($this->userA);
        $participantB = $this->thread->fresh()->getParticipant($this->userB);

        expect($participantA->role)->toBe(ParticipantRole::ADMIN->value)
            ->and($participantB->role)->toBe(ParticipantRole::OWNER->value);
    });
});
