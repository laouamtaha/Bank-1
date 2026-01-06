<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Facades\Chat;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);
    $this->thread = Chat::thread()->between($this->userA, $this->userB)->create();
});

describe('Message Bookmarks', function () {
    it('can save a message as bookmark', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Important message to save')
            ->send();

        $this->userB->saveItem($message);

        expect($this->userB->hasSavedItem($message))->toBeTrue();
    });

    it('can unsave a message', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Message to unsave')
            ->send();

        $this->userB->saveItem($message);
        $this->userB->unsaveItem($message);

        expect($this->userB->hasSavedItem($message))->toBeFalse();
    });

    it('can toggle save status', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Message to toggle')
            ->send();

        $this->userB->toggleSaveItem($message);
        expect($this->userB->hasSavedItem($message))->toBeTrue();

        $this->userB->toggleSaveItem($message);
        expect($this->userB->hasSavedItem($message))->toBeFalse();
    });

    it('can check if message is saved by user', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Check save status')
            ->send();

        expect($message->isSavedBy($this->userA))->toBeFalse();

        $this->userA->saveItem($message);

        expect($message->isSavedBy($this->userA))->toBeTrue()
            ->and($message->isSavedBy($this->userB))->toBeFalse();
    });

    it('can retrieve all saved messages', function () {
        $message1 = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Message 1')
            ->send();

        $message2 = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Message 2')
            ->send();

        $message3 = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Message 3')
            ->send();

        $this->userB->saveItem($message1);
        $this->userB->saveItem($message3);

        $savedMessages = $this->userB->savedItems(Message::class)->get();

        expect($savedMessages)->toHaveCount(2)
            ->and($savedMessages->pluck('id')->toArray())
            ->toContain($message1->id, $message3->id);
    });

    it('tracks how many times a message was saved', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Popular message')
            ->send();

        expect($message->timesSaved())->toBe(0);

        $this->userA->saveItem($message);
        expect($message->timesSaved())->toBe(1);

        $this->userB->saveItem($message);
        expect($message->timesSaved())->toBe(2);
    });

    it('allows multiple users to save same message independently', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Shared bookmark')
            ->send();

        $this->userA->saveItem($message);
        $this->userB->saveItem($message);

        expect($this->userA->hasSavedItem($message))->toBeTrue()
            ->and($this->userB->hasSavedItem($message))->toBeTrue();

        $this->userA->unsaveItem($message);

        expect($this->userA->hasSavedItem($message))->toBeFalse()
            ->and($this->userB->hasSavedItem($message))->toBeTrue();
    });

    it('can save messages with metadata', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Message with metadata')
            ->send();

        $this->userB->saveItem($message, metadata: ['note' => 'Remember this']);

        $savedRecord = $this->userB->getSavedRecord($message);

        expect($savedRecord)->not->toBeNull()
            ->and($savedRecord->metadata)->toBe(['note' => 'Remember this']);
    });
});

describe('Bookmark Collections', function () {
    it('can create a bookmark collection', function () {
        $collection = $this->userA->collections()->create(['name' => 'Important Messages']);

        expect($collection->name)->toBe('Important Messages')
            ->and($collection->owner_id)->toBe($this->userA->id)
            ->and($collection->owner_type)->toBe($this->userA->getMorphClass());
    });

    it('can save message to a specific collection', function () {
        $collection = $this->userA->collections()->create(['name' => 'Work']);

        $message = Chat::message()
            ->from($this->userB)
            ->to($this->thread)
            ->text('Work related message')
            ->send();

        $this->userA->saveItem($message, collection: $collection);

        $collectionItems = $collection->items();

        expect($collectionItems)->toHaveCount(1)
            ->and($collectionItems->first()->id)->toBe($message->id);
    });

    it('can retrieve all collections', function () {
        $this->userA->collections()->create(['name' => 'Personal']);
        $this->userA->collections()->create(['name' => 'Work']);
        $this->userA->collections()->create(['name' => 'Research']);

        $collections = $this->userA->collections()->get();

        expect($collections)->toHaveCount(3);
    });
});
