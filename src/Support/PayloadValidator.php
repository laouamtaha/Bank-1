<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Support;

use Ritechoice23\ChatEngine\Enums\MessageType;

class PayloadValidator
{
    /**
     * Validate a message payload.
     *
     * @throws \InvalidArgumentException
     */
    public function validate(array $payload, MessageType $type): void
    {
        match ($type) {
            MessageType::TEXT => $this->validateText($payload),
            MessageType::IMAGE => $this->validateImage($payload),
            MessageType::VIDEO => $this->validateVideo($payload),
            MessageType::AUDIO => $this->validateAudio($payload),
            MessageType::FILE => $this->validateFile($payload),
            MessageType::LOCATION => $this->validateLocation($payload),
            MessageType::CONTACT => $this->validateContact($payload),
            MessageType::SYSTEM => $this->validateSystem($payload),
            MessageType::CUSTOM => true, // No validation for custom payloads
        };
    }

    /**
     * Validate text message payload.
     */
    protected function validateText(array $payload): void
    {
        if (! isset($payload['content']) || ! is_string($payload['content'])) {
            throw new \InvalidArgumentException('Text message must have a string "content" field.');
        }

        if (empty(trim($payload['content']))) {
            throw new \InvalidArgumentException('Text message content cannot be empty.');
        }
    }

    /**
     * Validate image message payload.
     */
    protected function validateImage(array $payload): void
    {
        if (! isset($payload['url']) || ! is_string($payload['url'])) {
            throw new \InvalidArgumentException('Image message must have a string "url" field.');
        }

        if (! filter_var($payload['url'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Image message URL is not valid.');
        }
    }

    /**
     * Validate video message payload.
     */
    protected function validateVideo(array $payload): void
    {
        if (! isset($payload['url']) || ! is_string($payload['url'])) {
            throw new \InvalidArgumentException('Video message must have a string "url" field.');
        }

        if (! filter_var($payload['url'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Video message URL is not valid.');
        }
    }

    /**
     * Validate audio message payload.
     */
    protected function validateAudio(array $payload): void
    {
        if (! isset($payload['url']) || ! is_string($payload['url'])) {
            throw new \InvalidArgumentException('Audio message must have a string "url" field.');
        }

        if (! filter_var($payload['url'], FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Audio message URL is not valid.');
        }
    }

    /**
     * Validate file message payload.
     */
    protected function validateFile(array $payload): void
    {
        if (! isset($payload['url']) || ! is_string($payload['url'])) {
            throw new \InvalidArgumentException('File message must have a string "url" field.');
        }

        if (! isset($payload['filename']) || ! is_string($payload['filename'])) {
            throw new \InvalidArgumentException('File message must have a string "filename" field.');
        }
    }

    /**
     * Validate location message payload.
     */
    protected function validateLocation(array $payload): void
    {
        if (! isset($payload['latitude']) || ! is_numeric($payload['latitude'])) {
            throw new \InvalidArgumentException('Location message must have a numeric "latitude" field.');
        }

        if (! isset($payload['longitude']) || ! is_numeric($payload['longitude'])) {
            throw new \InvalidArgumentException('Location message must have a numeric "longitude" field.');
        }

        $lat = (float) $payload['latitude'];
        $lng = (float) $payload['longitude'];

        if ($lat < -90 || $lat > 90) {
            throw new \InvalidArgumentException('Latitude must be between -90 and 90.');
        }

        if ($lng < -180 || $lng > 180) {
            throw new \InvalidArgumentException('Longitude must be between -180 and 180.');
        }
    }

    /**
     * Validate contact message payload.
     */
    protected function validateContact(array $payload): void
    {
        if (! isset($payload['name']) || ! is_string($payload['name'])) {
            throw new \InvalidArgumentException('Contact message must have a string "name" field.');
        }

        // At least phone or email must be present
        $hasPhone = isset($payload['phone']) && is_string($payload['phone']);
        $hasEmail = isset($payload['email']) && is_string($payload['email']);

        if (! $hasPhone && ! $hasEmail) {
            throw new \InvalidArgumentException('Contact message must have either "phone" or "email" field.');
        }
    }

    /**
     * Validate system message payload.
     */
    protected function validateSystem(array $payload): void
    {
        if (! isset($payload['content']) || ! is_string($payload['content'])) {
            throw new \InvalidArgumentException('System message must have a string "content" field.');
        }
    }
}
