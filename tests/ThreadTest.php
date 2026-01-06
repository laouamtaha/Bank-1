<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Enums\ParticipantRole;
use Ritechoice23\ChatEngine\Enums\ThreadType;
use Ritechoice23\ChatEngine\Facades\Chat;
use Ritechoice23\ChatEngine\Models\Thread;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);
    $this->userC = User::create(['name' => 'User C', 'email' => 'c@test.com']);
});

describe('ThreadBuilder', function () {
    it('can create a direct thread between two users', function () {
        $thread = Chat::thread()
            ->between($this->userA, $this->userB)
            ->create();

        expect($thread)->toBeInstanceOf(Thread::class)
            ->and($thread->type)->toBe(ThreadType::DIRECT->value)
            ->and($thread->participants)->toHaveCount(2);
    });

    it('finds existing direct thread instead of creating duplicate', function () {
        $thread1 = Chat::thread()->between($this->userA, $this->userB)->create();
        $thread2 = Chat::thread()->between($this->userA, $this->userB)->create();

        expect($thread1->id)->toBe($thread2->id);
    });

    it('creates same thread regardless of participant order', function () {
        $thread1 = Chat::thread()->between($this->userA, $this->userB)->create();
        $thread2 = Chat::thread()->between($this->userB, $this->userA)->create();

        expect($thread1->id)->toBe($thread2->id);
    });

    it('can create a group thread', function () {
        $thread = Chat::thread()
            ->group('Engineering Team')
            ->withOwner($this->userA)
            ->participants([$this->userB, $this->userC])
            ->create();

        expect($thread->type)->toBe(ThreadType::GROUP->value)
            ->and($thread->name)->toBe('Engineering Team')
            ->and($thread->participants)->toHaveCount(3);
    });

    it('can create a channel thread', function () {
        $thread = Chat::thread()
            ->channel('Announcements')
            ->withOwner($this->userA)
            ->create();

        expect($thread->type)->toBe(ThreadType::CHANNEL->value)
            ->and($thread->name)->toBe('Announcements');
    });

    it('can add metadata to thread', function () {
        $thread = Chat::thread()
            ->group('Test')
            ->withOwner($this->userA)
            ->metadata(['project_id' => 123, 'priority' => 'high'])
            ->create();

        expect($thread->metadata)->toBe(['project_id' => 123, 'priority' => 'high']);
    });

    it('assigns correct roles to participants', function () {
        $thread = Chat::thread()
            ->group('Test')
            ->withOwner($this->userA)
            ->withAdmin($this->userB)
            ->withMember($this->userC)
            ->create();

        $participantA = $thread->getParticipant($this->userA);
        $participantB = $thread->getParticipant($this->userB);
        $participantC = $thread->getParticipant($this->userC);

        expect($participantA->role)->toBe(ParticipantRole::OWNER->value)
            ->and($participantB->role)->toBe(ParticipantRole::ADMIN->value)
            ->and($participantC->role)->toBe(ParticipantRole::MEMBER->value);
    });

    it('throws exception for direct thread with wrong participant count', function () {
        Chat::thread()
            ->type(ThreadType::DIRECT)
            ->participants([$this->userA])
            ->create();
    })->throws(InvalidArgumentException::class);

    it('can force create new thread with alwaysNew', function () {
        $thread1 = Chat::thread()->between($this->userA, $this->userB)->create();
        $thread2 = Chat::thread()->between($this->userA, $this->userB)->alwaysNew()->create();

        expect($thread1->id)->not->toBe($thread2->id);
    });
});

describe('Thread Model', function () {
    it('can check if actor is participant', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        expect($thread->hasParticipant($this->userA))->toBeTrue()
            ->and($thread->hasParticipant($this->userC))->toBeFalse();
    });

    it('can get active participants', function () {
        $thread = Chat::thread()
            ->group('Test')
            ->participants([$this->userA, $this->userB])
            ->create();

        $participant = $thread->getParticipant($this->userB);
        $participant->leave();

        expect($thread->activeParticipants()->count())->toBe(1);
    });

    it('cascades delete to participants and messages', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        Chat::message()->from($this->userA)->to($thread)->text('Hello')->send();

        $threadId = $thread->id;
        $thread->delete();

        expect(Thread::find($threadId))->toBeNull()
            ->and(\Ritechoice23\ChatEngine\Models\ThreadParticipant::where('thread_id', $threadId)->count())->toBe(0)
            ->and(\Ritechoice23\ChatEngine\Models\Message::where('thread_id', $threadId)->count())->toBe(0);
    });
});
