<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Actions\CreateThread;
use Ritechoice23\ChatEngine\Actions\SendMessage;
use Ritechoice23\ChatEngine\Enums\ParticipantRole;
use Ritechoice23\ChatEngine\Enums\ThreadType;
use Ritechoice23\ChatEngine\Policies\MessagePolicy;
use Ritechoice23\ChatEngine\Policies\ThreadPolicy;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);
    $this->userC = User::create(['name' => 'User C', 'email' => 'c@test.com']);
});

describe('ThreadPolicy', function () {
    it('allows participants to view thread', function () {
        $createThread = new CreateThread;
        $thread = $createThread->execute(
            type: ThreadType::GROUP,
            participants: [
                ['actor' => $this->userA, 'role' => ParticipantRole::OWNER],
                ['actor' => $this->userB, 'role' => ParticipantRole::MEMBER],
            ]
        );

        $policy = new ThreadPolicy;

        expect($policy->view($this->userA, $thread))->toBeTrue()
            ->and($policy->view($this->userB, $thread))->toBeTrue()
            ->and($policy->view($this->userC, $thread))->toBeFalse();
    });

    it('allows participants to send messages', function () {
        $createThread = new CreateThread;
        $thread = $createThread->direct($this->userA, $this->userB);

        $policy = new ThreadPolicy;

        expect($policy->sendMessage($this->userA, $thread))->toBeTrue()
            ->and($policy->sendMessage($this->userB, $thread))->toBeTrue()
            ->and($policy->sendMessage($this->userC, $thread))->toBeFalse();
    });

    it('allows only admins and owners to add participants', function () {
        $createThread = new CreateThread;
        $thread = $createThread->execute(
            type: ThreadType::GROUP,
            participants: [
                ['actor' => $this->userA, 'role' => ParticipantRole::OWNER],
                ['actor' => $this->userB, 'role' => ParticipantRole::MEMBER],
            ]
        );

        $policy = new ThreadPolicy;

        expect($policy->addParticipant($this->userA, $thread))->toBeTrue()
            ->and($policy->addParticipant($this->userB, $thread))->toBeFalse();
    });

    it('allows only admins and owners to remove participants', function () {
        $createThread = new CreateThread;
        $thread = $createThread->execute(
            type: ThreadType::GROUP,
            participants: [
                ['actor' => $this->userA, 'role' => ParticipantRole::OWNER],
                ['actor' => $this->userB, 'role' => ParticipantRole::ADMIN],
                ['actor' => $this->userC, 'role' => ParticipantRole::MEMBER],
            ]
        );

        $policy = new ThreadPolicy;

        expect($policy->removeParticipant($this->userA, $thread))->toBeTrue()
            ->and($policy->removeParticipant($this->userB, $thread))->toBeTrue()
            ->and($policy->removeParticipant($this->userC, $thread))->toBeFalse();
    });

    it('allows only owners to delete thread', function () {
        $createThread = new CreateThread;
        $thread = $createThread->execute(
            type: ThreadType::GROUP,
            participants: [
                ['actor' => $this->userA, 'role' => ParticipantRole::OWNER],
                ['actor' => $this->userB, 'role' => ParticipantRole::ADMIN],
            ]
        );

        $policy = new ThreadPolicy;

        expect($policy->delete($this->userA, $thread))->toBeTrue()
            ->and($policy->delete($this->userB, $thread))->toBeFalse();
    });

    it('denies non-participants from sending messages', function () {
        $createThread = new CreateThread;
        $thread = $createThread->direct($this->userA, $this->userB);

        $sendMessage = new SendMessage;

        expect(fn () => $sendMessage->text($thread, $this->userC, 'Hello'))
            ->toThrow(\Illuminate\Auth\Access\AuthorizationException::class);
    });
});

describe('MessagePolicy', function () {
    beforeEach(function () {
        $createThread = new CreateThread;
        $this->thread = $createThread->direct($this->userA, $this->userB);

        $sendMessage = new SendMessage;
        $this->message = $sendMessage->text($this->thread, $this->userA, 'Test message');
    });

    it('allows participants to view messages', function () {
        $policy = new MessagePolicy;

        expect($policy->view($this->userA, $this->message))->toBeTrue()
            ->and($policy->view($this->userB, $this->message))->toBeTrue()
            ->and($policy->view($this->userC, $this->message))->toBeFalse();
    });

    it('hides deleted messages', function () {
        $this->message->deleteGlobally($this->userA);

        $policy = new MessagePolicy;

        expect($policy->view($this->userA, $this->message->fresh()))->toBeFalse()
            ->and($policy->view($this->userB, $this->message->fresh()))->toBeFalse();
    });

    it('hides messages deleted for specific actor', function () {
        $this->message->deleteFor($this->userB);

        $policy = new MessagePolicy;

        expect($policy->view($this->userA, $this->message))->toBeTrue()
            ->and($policy->view($this->userB, $this->message))->toBeFalse();
    });

    it('allows only sender to edit their message', function () {
        $policy = new MessagePolicy;

        expect($policy->edit($this->userA, $this->message))->toBeTrue()
            ->and($policy->edit($this->userB, $this->message))->toBeFalse();
    });

    it('allows sender to delete message', function () {
        $policy = new MessagePolicy;

        expect($policy->delete($this->userA, $this->message))->toBeTrue();
    });

    it('allows admins to delete any message', function () {
        $createThread = new CreateThread;
        $thread = $createThread->execute(
            type: ThreadType::GROUP,
            participants: [
                ['actor' => $this->userA, 'role' => ParticipantRole::MEMBER],
                ['actor' => $this->userB, 'role' => ParticipantRole::ADMIN],
            ]
        );

        $sendMessage = new SendMessage;
        $message = $sendMessage->text($thread, $this->userA, 'Test');

        $policy = new MessagePolicy;

        expect($policy->delete($this->userB, $message))->toBeTrue();
    });

    it('allows participants to react to messages', function () {
        $policy = new MessagePolicy;

        expect($policy->react($this->userA, $this->message))->toBeTrue()
            ->and($policy->react($this->userB, $this->message))->toBeTrue()
            ->and($policy->react($this->userC, $this->message))->toBeFalse();
    });

    it('denies reactions on deleted messages', function () {
        $this->message->deleteGlobally($this->userA);

        $policy = new MessagePolicy;

        expect($policy->react($this->userB, $this->message->fresh()))->toBeFalse();
    });
});
