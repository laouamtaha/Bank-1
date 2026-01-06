<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Builders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Ritechoice23\ChatEngine\Actions\UploadAttachment;
use Ritechoice23\ChatEngine\Enums\AttachmentType;
use Ritechoice23\ChatEngine\Enums\MessageType;
use Ritechoice23\ChatEngine\Events\MessageSent;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Models\MessageAttachment;
use Ritechoice23\ChatEngine\Models\Thread;

class MessageBuilder
{
    protected ?Model $sender = null;

    protected ?Model $author = null;

    protected Thread|int|null $thread = null;

    protected MessageType $type = MessageType::TEXT;

    protected array $payload = [];

    protected bool $encrypted = false;

    protected ?string $encryptionDriver = null;

    protected array $pendingAttachments = [];

    /**
     * Set the message sender.
     */
    public function from(Model $actor): self
    {
        $this->sender = $actor;

        return $this;
    }

    /**
     * Set the author (when sending on behalf of someone).
     */
    public function onBehalfOf(Model $actor): self
    {
        $this->author = $actor;

        return $this;
    }

    /**
     * Set the target thread.
     */
    public function to(Thread|int $thread): self
    {
        $this->thread = $thread;

        return $this;
    }

    /**
     * Set the message type.
     */
    public function type(MessageType|string $type): self
    {
        $this->type = $type instanceof MessageType
            ? $type
            : MessageType::from($type);

        return $this;
    }

    /**
     * Create a text message.
     */
    public function text(string $content): self
    {
        $this->type = MessageType::TEXT;
        $this->payload = [
            'type' => MessageType::TEXT->value,
            'content' => $content,
        ];

        return $this;
    }

    /**
     * Create an image message.
     */
    public function image(string $url, ?string $caption = null, ?int $width = null, ?int $height = null): self
    {
        $this->type = MessageType::IMAGE;
        $this->payload = array_filter([
            'type' => MessageType::IMAGE->value,
            'url' => $url,
            'caption' => $caption,
            'width' => $width,
            'height' => $height,
        ], fn ($value) => $value !== null);

        return $this;
    }

    /**
     * Create a video message.
     */
    public function video(string $url, ?string $thumbnail = null, ?int $duration = null): self
    {
        $this->type = MessageType::VIDEO;
        $this->payload = array_filter([
            'type' => MessageType::VIDEO->value,
            'url' => $url,
            'thumbnail' => $thumbnail,
            'duration' => $duration,
        ], fn ($value) => $value !== null);

        return $this;
    }

    /**
     * Create an audio message.
     */
    public function audio(string $url, ?int $duration = null, ?string $waveform = null): self
    {
        $this->type = MessageType::AUDIO;
        $this->payload = array_filter([
            'type' => MessageType::AUDIO->value,
            'url' => $url,
            'duration' => $duration,
            'waveform' => $waveform,
        ], fn ($value) => $value !== null);

        return $this;
    }

    /**
     * Create a file/document message.
     */
    public function file(string $url, string $filename, ?string $mimeType = null, ?int $size = null): self
    {
        $this->type = MessageType::FILE;
        $this->payload = array_filter([
            'type' => MessageType::FILE->value,
            'url' => $url,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'size' => $size,
        ], fn ($value) => $value !== null);

        return $this;
    }

    /**
     * Create a location message.
     */
    public function location(float $latitude, float $longitude, ?string $address = null, ?string $name = null): self
    {
        $this->type = MessageType::LOCATION;
        $this->payload = array_filter([
            'type' => MessageType::LOCATION->value,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address,
            'name' => $name,
        ], fn ($value) => $value !== null);

        return $this;
    }

    /**
     * Create a contact message.
     */
    public function contact(string $name, ?string $phone = null, ?string $email = null, ?array $extra = null): self
    {
        $this->type = MessageType::CONTACT;
        $this->payload = array_filter([
            'type' => MessageType::CONTACT->value,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'extra' => $extra,
        ], fn ($value) => $value !== null);

        return $this;
    }

    /**
     * Create a system message.
     */
    public function system(string $content, ?string $action = null): self
    {
        $this->type = MessageType::SYSTEM;
        $this->payload = array_filter([
            'type' => MessageType::SYSTEM->value,
            'content' => $content,
            'action' => $action,
        ], fn ($value) => $value !== null);

        return $this;
    }

    /**
     * Set custom payload.
     */
    public function payload(array $payload): self
    {
        $this->payload = array_merge($this->payload, $payload);

        return $this;
    }

    /**
     * Mark message as encrypted.
     */
    public function encrypted(string $driver = 'default'): self
    {
        $this->encrypted = true;
        $this->encryptionDriver = $driver;

        return $this;
    }

    /**
     * Add a single attachment to the message.
     *
     * @param  array{disk?: string, filename?: string, mime_type?: string, size?: int, duration?: int, width?: int, height?: int, thumbnail_path?: string, blurhash?: string, caption?: string, view_once?: bool, metadata?: array}  $options
     */
    public function attach(string $path, AttachmentType|string $type, array $options = []): self
    {
        $typeValue = $type instanceof AttachmentType ? $type->value : $type;

        $this->pendingAttachments[] = array_merge($options, [
            'path' => $path,
            'type' => $typeValue,
            'disk' => $options['disk'] ?? config('chat-engine.attachments.disk', 'public'),
        ]);

        return $this;
    }

    /**
     * Add multiple attachments to the message.
     *
     * @param  array<array{path: string, type: string, disk?: string, filename?: string, ...}>  $attachments
     */
    public function attachments(array $attachments): self
    {
        foreach ($attachments as $attachment) {
            $this->attach(
                $attachment['path'],
                $attachment['type'],
                $attachment
            );
        }

        return $this;
    }

    /**
     * Add a view-once attachment (self-destructs after viewing).
     */
    public function viewOnce(string $path, AttachmentType|string $type, array $options = []): self
    {
        return $this->attach($path, $type, array_merge($options, ['view_once' => true]));
    }

    /**
     * Upload and attach a file directly from a Laravel request.
     *
     * @param  array{caption?: string, view_once?: bool, metadata?: array}  $options
     */
    public function attachUpload(
        UploadedFile $file,
        ?AttachmentType $type = null,
        array $options = []
    ): self {
        $data = app(UploadAttachment::class)->execute($file, $type);

        return $this->attach($data['path'], $data['type'], array_merge($data, $options));
    }

    /**
     * Upload and attach multiple files directly from a Laravel request.
     *
     * @param  array<UploadedFile>  $files
     * @param  array{caption?: string, view_once?: bool, metadata?: array}  $options  Applied to all files
     */
    public function attachUploads(array $files, array $options = []): self
    {
        foreach ($files as $file) {
            $this->attachUpload($file, null, $options);
        }

        return $this;
    }

    /**
     * Upload and attach a view-once file directly from a Laravel request.
     */
    public function attachUploadViewOnce(UploadedFile $file, ?AttachmentType $type = null): self
    {
        return $this->attachUpload($file, $type, ['view_once' => true]);
    }

    /**
     * Send the message.
     */
    public function send(): Message
    {
        $this->validate();

        $threadId = $this->thread instanceof Thread
            ? $this->thread->id
            : $this->thread;

        $messageModel = config('chat-engine.models.message', Message::class);

        $message = $messageModel::create([
            'thread_id' => $threadId,
            'sender_type' => $this->sender->getMorphClass(),
            'sender_id' => $this->sender->getKey(),
            'author_type' => $this->author?->getMorphClass(),
            'author_id' => $this->author?->getKey(),
            'type' => $this->type->value,
            'payload' => $this->payload,
            'encrypted' => $this->encrypted,
            'encryption_driver' => $this->encryptionDriver,
        ]);

        // Create attachments
        $this->createAttachments($message);

        // Auto-mark as read by sender
        $message->markAsReadBy($this->sender);

        // Get the thread for the event
        $thread = $this->thread instanceof Thread
            ? $this->thread
            : Thread::find($threadId);

        MessageSent::dispatch($message, $thread, $this->sender);

        return $message;
    }

    /**
     * Create attachment records for the message.
     */
    protected function createAttachments(Message $message): void
    {
        if (empty($this->pendingAttachments)) {
            return;
        }

        $maxAttachments = config('chat-engine.attachments.max_per_message', 10);
        $attachments = array_slice($this->pendingAttachments, 0, $maxAttachments);

        $attachmentModel = config('chat-engine.models.message_attachment', MessageAttachment::class);

        foreach ($attachments as $order => $data) {
            $attachmentModel::create([
                'message_id' => $message->id,
                'type' => $data['type'],
                'disk' => $data['disk'] ?? config('chat-engine.attachments.disk', 'public'),
                'path' => $data['path'],
                'filename' => $data['filename'] ?? null,
                'mime_type' => $data['mime_type'] ?? null,
                'size' => $data['size'] ?? null,
                'duration' => $data['duration'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
                'thumbnail_path' => $data['thumbnail_path'] ?? null,
                'blurhash' => $data['blurhash'] ?? null,
                'caption' => $data['caption'] ?? null,
                'view_once' => $data['view_once'] ?? false,
                'metadata' => $data['metadata'] ?? null,
                'order' => $order,
            ]);
        }
    }

    protected function validate(): void
    {
        if ($this->sender === null) {
            throw new \InvalidArgumentException('Message must have a sender. Use ->from($actor)');
        }

        if ($this->thread === null) {
            throw new \InvalidArgumentException('Message must have a target thread. Use ->to($thread)');
        }

        // Allow messages with only attachments (no payload required)
        if (empty($this->payload) && empty($this->pendingAttachments)) {
            throw new \InvalidArgumentException('Message must have a payload or attachments.');
        }

        // Check if thread is locked
        $thread = $this->thread instanceof Thread
            ? $this->thread
            : Thread::find($this->thread);

        if ($thread && ! $thread->canSendMessage($this->sender)) {
            throw new \InvalidArgumentException('Thread is locked. Only admins can send messages.');
        }
    }
}
