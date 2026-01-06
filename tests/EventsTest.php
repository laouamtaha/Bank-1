<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Ritechoice23\ChatEngine\Events\MessageDeletedForActor;
use Ritechoice23\ChatEngine\Events\MessageDeletedGlobally;
use Ritechoice23\ChatEngine\Events\MessageDelivered;
use Ritechoice23\ChatEngine\Events\MessageEdited;
use Ritechoice23\ChatEngine\Events\MessageRead;
use Ritechoice23\ChatEngine\Events\MessageRestored;
use Ritechoice23\ChatEngine\Events\MessageSent;
use Ritechoice23\ChatEngine\Events\ParticipantAdded;
use Ritechoice23\ChatEngine\Events\ParticipantRemoved;
use Ritechoice23\ChatEngine\Events\ThreadCreated;
use Ritechoice23\ChatEngine\Events\TypingStarted;
use Ritechoice23\ChatEngine\Events\TypingStopped;
use Ritechoice23\ChatEngine\Facades\Chat;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);
});

describe('Thread Events', function () {
    it('dispatches ThreadCreated when thread is created', function () {
        Event::fake([ThreadCreated::class]);

        $thread = Chat::thread()
            ->between($this->userA, $this->userB)
            ->create();

        Event::assertDispatched(ThreadCreated::class, function ($event) use ($thread) {
            return $event->thread->id === $thread->id;
        });
    });

    it('dispatches ParticipantAdded for each participant', function () {
        Event::fake([ParticipantAdded::class]);

        Chat::thread()
            ->between($this->userA, $this->userB)
            ->create();

        Event::assertDispatchedTimes(ParticipantAdded::class, 2);
    });

    it('dispatches ParticipantAdded when adding participant via Chat facade', function () {
        $thread = Chat::thread()
            ->group('Test')
            ->withOwner($this->userA)
            ->create();

        Event::fake([ParticipantAdded::class]);
        Chat::addParticipant($thread, $this->userB);

        Event::assertDispatched(ParticipantAdded::class, function ($event) use ($thread) {
            return $event->thread->id === $thread->id
                && $event->actor->id === $this->userB->id;
        });
    });

    it('dispatches ParticipantRemoved when removing participant', function () {
        $thread = Chat::thread()
            ->group('Test')
            ->participants([$this->userA, $this->userB])
            ->create();

        Event::fake([ParticipantRemoved::class]);
        Chat::removeParticipant($thread, $this->userB);

        Event::assertDispatched(ParticipantRemoved::class, function ($event) use ($thread) {
            return $event->thread->id === $thread->id
                && $event->actor->id === $this->userB->id;
        });
    });
});

describe('Message Events', function () {
    it('dispatches MessageSent when message is sent', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        Event::fake([MessageSent::class]);

        $message = Chat::message()
            ->from($this->userA)
            ->to($thread)
            ->text('Hello')
            ->send();

        Event::assertDispatched(MessageSent::class, function ($event) use ($message) {
            return $event->message->id === $message->id
                && $event->sender->id === $this->userA->id;
        });
    });

    it('dispatches MessageEdited when message is edited', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        $message = Chat::message()
            ->from($this->userA)
            ->to($thread)
            ->text('Original')
            ->send();

        Event::fake([MessageEdited::class]);
        $message->edit(['type' => 'text', 'content' => 'Edited'], $this->userA);

        Event::assertDispatched(MessageEdited::class, function ($event) use ($message) {
            return $event->message->id === $message->id;
        });
    });

    it('dispatches MessageDelivered when marked as delivered', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        $message = Chat::message()
            ->from($this->userA)
            ->to($thread)
            ->text('Hello')
            ->send();

        Event::fake([MessageDelivered::class]);
        $message->markAsDeliveredTo($this->userB);

        Event::assertDispatched(MessageDelivered::class, function ($event) use ($message) {
            return $event->message->id === $message->id
                && $event->actor->id === $this->userB->id;
        });
    });

    it('dispatches MessageRead when marked as read', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        $message = Chat::message()
            ->from($this->userA)
            ->to($thread)
            ->text('Hello')
            ->send();

        Event::fake([MessageRead::class]);
        $message->markAsReadBy($this->userB);

        Event::assertDispatched(MessageRead::class, function ($event) use ($message) {
            return $event->message->id === $message->id
                && $event->actor->id === $this->userB->id;
        });
    });

    it('dispatches MessageDeletedForActor when deleted for self', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        $message = Chat::message()
            ->from($this->userA)
            ->to($thread)
            ->text('Hello')
            ->send();

        Event::fake([MessageDeletedForActor::class]);
        $message->deleteFor($this->userB);

        Event::assertDispatched(MessageDeletedForActor::class, function ($event) use ($message) {
            return $event->message->id === $message->id
                && $event->actor->id === $this->userB->id;
        });
    });

    it('dispatches MessageDeletedGlobally when deleted for everyone', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        $message = Chat::message()
            ->from($this->userA)
            ->to($thread)
            ->text('Hello')
            ->send();

        Event::fake([MessageDeletedGlobally::class]);
        $message->deleteGlobally($this->userA);

        Event::assertDispatched(MessageDeletedGlobally::class, function ($event) use ($message) {
            return $event->message->id === $message->id
                && $event->deletedBy->id === $this->userA->id;
        });
    });

    it('dispatches MessageRestored when restored for self', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        $message = Chat::message()
            ->from($this->userA)
            ->to($thread)
            ->text('Hello')
            ->send();

        $message->deleteFor($this->userB);

        Event::fake([MessageRestored::class]);
        $message->restoreFor($this->userB);

        Event::assertDispatched(MessageRestored::class, function ($event) use ($message) {
            return $event->message->id === $message->id
                && $event->actor->id === $this->userB->id;
        });
    });
});

describe('Presence Events', function () {
    it('dispatches TypingStarted', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        Event::fake([TypingStarted::class]);
        Chat::startTyping($thread, $this->userA);

        Event::assertDispatched(TypingStarted::class, function ($event) use ($thread) {
            return $event->thread->id === $thread->id
                && $event->actor->id === $this->userA->id;
        });
    });

    it('dispatches TypingStopped', function () {
        $thread = Chat::thread()->between($this->userA, $this->userB)->create();

        Event::fake([TypingStopped::class]);
        Chat::stopTyping($thread, $this->userA);

        Event::assertDispatched(TypingStopped::class, function ($event) use ($thread) {
            return $event->thread->id === $thread->id
                && $event->actor->id === $this->userA->id;
        });
    });
});
