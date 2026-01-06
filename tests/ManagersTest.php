<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Ritechoice23\ChatEngine\Actions\CreateThread;
use Ritechoice23\ChatEngine\Actions\SendMessage;
use Ritechoice23\ChatEngine\Events\TypingStarted;
use Ritechoice23\ChatEngine\Events\TypingStopped;
use Ritechoice23\ChatEngine\Support\PresenceManager;
use Ritechoice23\ChatEngine\Support\RetentionManager;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);

    $createThread = new CreateThread;
    $this->thread = $createThread->direct($this->userA, $this->userB);
});

describe('PresenceManager', function () {
    it('dispatches typing started event', function () {
        Event::fake([TypingStarted::class]);

        $presence = new PresenceManager;
        $presence->typing($this->userA, $this->thread);

        Event::assertDispatched(TypingStarted::class, function ($event) {
            return $event->thread->id === $this->thread->id
                && $event->actor->id === $this->userA->id;
        });
    });

    it('dispatches typing stopped event', function () {
        Event::fake([TypingStopped::class]);

        $presence = new PresenceManager;
        $presence->stopTyping($this->userA, $this->thread);

        Event::assertDispatched(TypingStopped::class);
    });

    it('dispatches online event', function () {
        Event::fake();

        $presence = new PresenceManager;
        $presence->online($this->userA);

        Event::assertDispatched('chat.presence.online');
    });

    it('dispatches offline event', function () {
        Event::fake();

        $presence = new PresenceManager;
        $presence->offline($this->userA);

        Event::assertDispatched('chat.presence.offline');
    });

    it('dispatches away event', function () {
        Event::fake();

        $presence = new PresenceManager;
        $presence->away($this->userA);

        Event::assertDispatched('chat.presence.away');
    });
});

describe('RetentionManager', function () {
    it('purges deleted messages older than days', function () {
        $sendMessage = new SendMessage;
        $message = $sendMessage->text($this->thread, $this->userA, 'Delete me');

        // Soft delete
        $message->deleteGlobally($this->userA);

        // Set deleted_at to 31 days ago
        $message->update(['deleted_at' => now()->subDays(31)]);

        $retention = new RetentionManager;
        $count = $retention->purgeDeletedMessages(30);

        expect($count)->toBeGreaterThan(0);
    });

    it('does not purge recent deleted messages', function () {
        $sendMessage = new SendMessage;
        $message = $sendMessage->text($this->thread, $this->userA, 'Keep me');

        $message->deleteGlobally($this->userA);

        $retention = new RetentionManager;
        $count = $retention->purgeDeletedMessages(30);

        expect($count)->toBe(0);
    });

    it('purges old delivery records', function () {
        $sendMessage = new SendMessage;
        $message = $sendMessage->text($this->thread, $this->userA, 'Test');

        // Create read delivery 31 days ago
        $delivery = $message->markAsReadBy($this->userB);
        $delivery->update(['read_at' => now()->subDays(31)]);

        $retention = new RetentionManager;
        $count = $retention->purgeOldDeliveries(30);

        expect($count)->toBeGreaterThan(0);
    });
});
