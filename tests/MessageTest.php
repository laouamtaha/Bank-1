<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Enums\MessageType;
use Ritechoice23\ChatEngine\Facades\Chat;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);
    $this->thread = Chat::thread()->between($this->userA, $this->userB)->create();
});

describe('MessageBuilder', function () {
    it('can send a text message', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Hello, World!')
            ->send();

        expect($message)->toBeInstanceOf(Message::class)
            ->and($message->type)->toBe(MessageType::TEXT->value)
            ->and($message->payload['content'])->toBe('Hello, World!');
    });

    it('can send an image message', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->image('https://example.com/image.jpg', 'Beautiful sunset')
            ->send();

        expect($message->type)->toBe(MessageType::IMAGE->value)
            ->and($message->payload['url'])->toBe('https://example.com/image.jpg')
            ->and($message->payload['caption'])->toBe('Beautiful sunset');
    });

    it('can send a video message', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->video('https://example.com/video.mp4', 'https://example.com/thumb.jpg', 120)
            ->send();

        expect($message->type)->toBe(MessageType::VIDEO->value)
            ->and($message->payload['url'])->toBe('https://example.com/video.mp4')
            ->and($message->payload['duration'])->toBe(120);
    });

    it('can send an audio message', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->audio('https://example.com/audio.mp3', 180)
            ->send();

        expect($message->type)->toBe(MessageType::AUDIO->value)
            ->and($message->payload['duration'])->toBe(180);
    });

    it('can send a file message', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->file('https://example.com/doc.pdf', 'report.pdf', 'application/pdf', 1024)
            ->send();

        expect($message->type)->toBe(MessageType::FILE->value)
            ->and($message->payload['filename'])->toBe('report.pdf')
            ->and($message->payload['mime_type'])->toBe('application/pdf');
    });

    it('can send a location message', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->location(40.7128, -74.0060, 'New York, NY')
            ->send();

        expect($message->type)->toBe(MessageType::LOCATION->value)
            ->and($message->payload['latitude'])->toBe(40.7128)
            ->and($message->payload['longitude'])->toBe(-74.0060);
    });

    it('can send a contact message', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->contact('John Doe', '+1234567890', 'john@example.com')
            ->send();

        expect($message->type)->toBe(MessageType::CONTACT->value)
            ->and($message->payload['name'])->toBe('John Doe')
            ->and($message->payload['phone'])->toBe('+1234567890');
    });

    it('can send a system message', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->system('User A joined the chat', 'user_joined')
            ->send();

        expect($message->type)->toBe(MessageType::SYSTEM->value)
            ->and($message->payload['action'])->toBe('user_joined');
    });

    it('auto-marks message as read by sender', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Hello')
            ->send();

        expect($message->isReadBy($this->userA))->toBeTrue()
            ->and($message->isReadBy($this->userB))->toBeFalse();
    });

    it('throws exception without sender', function () {
        Chat::message()->to($this->thread)->text('Hello')->send();
    })->throws(InvalidArgumentException::class);

    it('throws exception without thread', function () {
        Chat::message()->from($this->userA)->text('Hello')->send();
    })->throws(InvalidArgumentException::class);
});

describe('Message Model', function () {
    it('can edit message in immutable mode', function () {
        config()->set('chat-engine.messages.immutable', true);

        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Original')
            ->send();

        $message->edit(['type' => 'text', 'content' => 'Edited'], $this->userA);

        expect($message->isEdited)->toBeTrue()
            ->and($message->currentPayload['content'])->toBe('Edited')
            ->and($message->payload['content'])->toBe('Original')
            ->and($message->versions()->count())->toBe(1);
    });

    it('can edit message in mutable mode', function () {
        config()->set('chat-engine.messages.immutable', false);

        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Original')
            ->send();

        $message->edit(['type' => 'text', 'content' => 'Edited'], $this->userA);

        expect($message->isEdited)->toBeFalse()
            ->and($message->payload['content'])->toBe('Edited')
            ->and($message->versions()->count())->toBe(0);
    });

    it('can delete message for specific actor', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Hello')
            ->send();

        $message->deleteFor($this->userB);

        expect($message->isDeletedFor($this->userB))->toBeTrue()
            ->and($message->isDeletedFor($this->userA))->toBeFalse();
    });

    it('can restore message for specific actor', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Hello')
            ->send();

        $message->deleteFor($this->userB);
        $message->restoreFor($this->userB);

        expect($message->isDeletedFor($this->userB))->toBeFalse();
    });

    it('can delete message globally', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Hello')
            ->send();

        $message->deleteGlobally($this->userA);

        expect($message->isDeleted)->toBeTrue()
            ->and($message->isDeletedFor($this->userA))->toBeTrue()
            ->and($message->isDeletedFor($this->userB))->toBeTrue();
    });

    it('can track delivery status', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Hello')
            ->send();

        expect($message->isDeliveredTo($this->userB))->toBeFalse();

        $message->markAsDeliveredTo($this->userB);

        expect($message->isDeliveredTo($this->userB))->toBeTrue()
            ->and($message->isReadBy($this->userB))->toBeFalse();
    });

    it('can track read status', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Hello')
            ->send();

        $message->markAsReadBy($this->userB);

        expect($message->isDeliveredTo($this->userB))->toBeTrue()
            ->and($message->isReadBy($this->userB))->toBeTrue();
    });

    it('can check if sent by actor', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Hello')
            ->send();

        expect($message->isSentBy($this->userA))->toBeTrue()
            ->and($message->isSentBy($this->userB))->toBeFalse();
    });
});
