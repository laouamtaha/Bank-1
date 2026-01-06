<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Enums\MessageType;
use Ritechoice23\ChatEngine\Support\PayloadValidator;

describe('PayloadValidator', function () {
    beforeEach(function () {
        $this->validator = new PayloadValidator;
    });

    it('validates text messages', function () {
        $this->validator->validate(['content' => 'Hello'], MessageType::TEXT);
        expect(true)->toBeTrue(); // No exception
    });

    it('rejects empty text content', function () {
        $this->validator->validate(['content' => ''], MessageType::TEXT);
    })->throws(InvalidArgumentException::class, 'empty');

    it('rejects text without content field', function () {
        $this->validator->validate(['text' => 'Hello'], MessageType::TEXT);
    })->throws(InvalidArgumentException::class, 'content');

    it('validates image messages', function () {
        $this->validator->validate(['url' => 'https://example.com/image.jpg'], MessageType::IMAGE);
        expect(true)->toBeTrue();
    });

    it('rejects invalid image URLs', function () {
        $this->validator->validate(['url' => 'not-a-url'], MessageType::IMAGE);
    })->throws(InvalidArgumentException::class, 'valid');

    it('validates video messages', function () {
        $this->validator->validate([
            'url' => 'https://example.com/video.mp4',
            'thumbnail' => 'https://example.com/thumb.jpg',
            'duration' => 120,
        ], MessageType::VIDEO);
        expect(true)->toBeTrue();
    });

    it('validates audio messages', function () {
        $this->validator->validate(['url' => 'https://example.com/audio.mp3'], MessageType::AUDIO);
        expect(true)->toBeTrue();
    });

    it('validates file messages', function () {
        $this->validator->validate([
            'url' => 'https://example.com/doc.pdf',
            'filename' => 'document.pdf',
            'mimeType' => 'application/pdf',
            'size' => 1024,
        ], MessageType::FILE);
        expect(true)->toBeTrue();
    });

    it('rejects file without filename', function () {
        $this->validator->validate(['url' => 'https://example.com/doc.pdf'], MessageType::FILE);
    })->throws(InvalidArgumentException::class, 'filename');

    it('validates location messages', function () {
        $this->validator->validate([
            'latitude' => 40.7128,
            'longitude' => -74.0060,
            'address' => 'New York, NY',
        ], MessageType::LOCATION);
        expect(true)->toBeTrue();
    });

    it('rejects invalid latitude', function () {
        $this->validator->validate([
            'latitude' => 100,
            'longitude' => -74.0060,
        ], MessageType::LOCATION);
    })->throws(InvalidArgumentException::class, 'Latitude');

    it('rejects invalid longitude', function () {
        $this->validator->validate([
            'latitude' => 40.7128,
            'longitude' => 200,
        ], MessageType::LOCATION);
    })->throws(InvalidArgumentException::class, 'Longitude');

    it('validates contact messages with phone', function () {
        $this->validator->validate([
            'name' => 'John Doe',
            'phone' => '+1234567890',
        ], MessageType::CONTACT);
        expect(true)->toBeTrue();
    });

    it('validates contact messages with email', function () {
        $this->validator->validate([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ], MessageType::CONTACT);
        expect(true)->toBeTrue();
    });

    it('rejects contact without phone or email', function () {
        $this->validator->validate(['name' => 'John Doe'], MessageType::CONTACT);
    })->throws(InvalidArgumentException::class, 'phone');

    it('validates system messages', function () {
        $this->validator->validate(['content' => 'User joined'], MessageType::SYSTEM);
        expect(true)->toBeTrue();
    });

    it('allows any payload for custom type', function () {
        $this->validator->validate(['anything' => 'goes', 'here' => true], MessageType::CUSTOM);
        expect(true)->toBeTrue();
    });
});
