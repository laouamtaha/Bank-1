<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Enums\AttachmentType;
use Ritechoice23\ChatEngine\Facades\Chat;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Models\MessageAttachment;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);
    $this->thread = Chat::thread()->between($this->userA, $this->userB)->create();
});

describe('MessageBuilder Attachments', function () {
    it('can send a message with a single attachment', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Check this out!')
            ->attach('images/photo.jpg', AttachmentType::IMAGE, [
                'filename' => 'sunset.jpg',
                'caption' => 'Beautiful sunset',
                'disk' => 'public',
            ])
            ->send();

        expect($message)->toBeInstanceOf(Message::class)
            ->and($message->attachments)->toHaveCount(1);

        $attachment = $message->attachments->first();
        expect($attachment->type)->toBe('image')
            ->and($attachment->path)->toBe('images/photo.jpg')
            ->and($attachment->filename)->toBe('sunset.jpg')
            ->and($attachment->caption)->toBe('Beautiful sunset');
    });

    it('can send a message with multiple attachments', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Here are the files')
            ->attachments([
                ['path' => 'images/img1.jpg', 'type' => 'image', 'disk' => 'public'],
                ['path' => 'images/img2.jpg', 'type' => 'image', 'disk' => 'public'],
                ['path' => 'docs/report.pdf', 'type' => 'file', 'filename' => 'report.pdf'],
            ])
            ->send();

        expect($message->attachments)->toHaveCount(3);

        // Check order is preserved
        $attachments = $message->attachments;
        expect($attachments[0]->order)->toBe(0)
            ->and($attachments[1]->order)->toBe(1)
            ->and($attachments[2]->order)->toBe(2);
    });

    it('can send attachment-only message without text', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->attach('audio/voice.mp3', AttachmentType::AUDIO, [
                'duration' => 30,
            ])
            ->send();

        expect($message)->toBeInstanceOf(Message::class)
            ->and($message->payload)->toBeEmpty()
            ->and($message->attachments)->toHaveCount(1);
    });

    it('can send view-once attachment', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->viewOnce('images/secret.jpg', AttachmentType::IMAGE)
            ->send();

        $attachment = $message->attachments->first();
        expect($attachment->view_once)->toBeTrue()
            ->and($attachment->viewed_at)->toBeNull()
            ->and($attachment->is_consumed)->toBeFalse();
    });

    it('respects max attachments limit', function () {
        config()->set('chat-engine.attachments.max_per_message', 3);

        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Too many files')
            ->attachments([
                ['path' => 'file1.jpg', 'type' => 'image'],
                ['path' => 'file2.jpg', 'type' => 'image'],
                ['path' => 'file3.jpg', 'type' => 'image'],
                ['path' => 'file4.jpg', 'type' => 'image'],
                ['path' => 'file5.jpg', 'type' => 'image'],
            ])
            ->send();

        // Only first 3 should be saved
        expect($message->attachments)->toHaveCount(3);
    });

    it('throws exception without payload or attachments', function () {
        Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->send();
    })->throws(InvalidArgumentException::class, 'payload or attachments');
});

describe('MessageAttachment Model', function () {
    beforeEach(function () {
        $this->message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Test')
            ->attach('images/test.jpg', AttachmentType::IMAGE, [
                'filename' => 'test.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 1024000,
            ])
            ->send();

        $this->attachment = $this->message->attachments->first();
    });

    it('generates human readable file size', function () {
        expect($this->attachment->human_size)->toBe('1000 KB');
    });

    it('can consume view-once attachment', function () {
        $attachment = MessageAttachment::create([
            'message_id' => $this->message->id,
            'type' => 'image',
            'disk' => 'public',
            'path' => 'secret/once.jpg',
            'view_once' => true,
        ]);

        expect($attachment->isAccessible())->toBeTrue();

        // First consume returns URL
        $url = $attachment->consume();
        expect($url)->not->toBeNull();

        // Refresh and check consumed
        $attachment->refresh();
        expect($attachment->is_consumed)->toBeTrue()
            ->and($attachment->viewed_at)->not->toBeNull();

        // Second consume returns null
        expect($attachment->consume())->toBeNull();
    });

    it('belongs to a message', function () {
        expect($this->attachment->message->id)->toBe($this->message->id);
    });

    it('stores optional metadata', function () {
        $attachment = MessageAttachment::create([
            'message_id' => $this->message->id,
            'type' => 'video',
            'disk' => 'public',
            'path' => 'videos/clip.mp4',
            'duration' => 120,
            'width' => 1920,
            'height' => 1080,
            'blurhash' => 'LEHV6nWB2yk8pyo0adR*.7kCMdnj',
            'metadata' => ['codec' => 'h264', 'fps' => 30],
        ]);

        expect($attachment->duration)->toBe(120)
            ->and($attachment->width)->toBe(1920)
            ->and($attachment->height)->toBe(1080)
            ->and($attachment->blurhash)->toBe('LEHV6nWB2yk8pyo0adR*.7kCMdnj')
            ->and($attachment->metadata['codec'])->toBe('h264');
    });

    it('generates human readable duration', function () {
        $attachment = MessageAttachment::create([
            'message_id' => $this->message->id,
            'type' => 'audio',
            'disk' => 'public',
            'path' => 'audio/song.mp3',
            'duration' => 185,
        ]);

        expect($attachment->human_duration)->toBe('3:05');
    });
});

describe('Message Attachments Relationship', function () {
    it('can query messages with attachments eagerly loaded', function () {
        Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('With attachment')
            ->attach('file.jpg', AttachmentType::IMAGE)
            ->send();

        $messages = Message::with('attachments')
            ->inThread($this->thread)
            ->get();

        expect($messages)->toHaveCount(1)
            ->and($messages->first()->relationLoaded('attachments'))->toBeTrue();
    });

    it('deletes attachments when message is deleted', function () {
        $message = Chat::message()
            ->from($this->userA)
            ->to($this->thread)
            ->text('Will be deleted')
            ->attach('file1.jpg', AttachmentType::IMAGE)
            ->attach('file2.jpg', AttachmentType::IMAGE)
            ->send();

        $attachmentIds = $message->attachments->pluck('id')->toArray();

        $message->delete();

        foreach ($attachmentIds as $id) {
            expect(MessageAttachment::find($id))->toBeNull();
        }
    });
});

describe('AttachmentType Enum', function () {
    it('detects media with duration', function () {
        expect(AttachmentType::VIDEO->hasDuration())->toBeTrue()
            ->and(AttachmentType::AUDIO->hasDuration())->toBeTrue()
            ->and(AttachmentType::IMAGE->hasDuration())->toBeFalse()
            ->and(AttachmentType::FILE->hasDuration())->toBeFalse();
    });

    it('detects media with dimensions', function () {
        expect(AttachmentType::IMAGE->hasDimensions())->toBeTrue()
            ->and(AttachmentType::VIDEO->hasDimensions())->toBeTrue()
            ->and(AttachmentType::AUDIO->hasDimensions())->toBeFalse();
    });

    it('detects BlurHash support', function () {
        expect(AttachmentType::IMAGE->supportsBlurHash())->toBeTrue()
            ->and(AttachmentType::VIDEO->supportsBlurHash())->toBeTrue()
            ->and(AttachmentType::FILE->supportsBlurHash())->toBeFalse();
    });
});
