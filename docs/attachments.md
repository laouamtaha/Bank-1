# Attachments

Send files, images, videos, and audio with messages — just like WhatsApp.

## Overview

The attachment system stores **disk and path** (not raw URLs), enabling:
- Disk migration without database changes
- Signed/temporary URLs for private storage
- View-once media that self-destructs

## Configuration

```php
// config/chat-engine.php
'attachments' => [
    'disk' => env('CHAT_FILESYSTEM_DISK', 'public'),  // Default disk
    'path' => 'chat-attachments',                     // Storage prefix
    'visibility' => 'public',                         // File visibility
    'max_per_message' => 10,                          // Attachment limit
    'allowed_types' => ['image', 'video', 'audio', 'file'],
    'delete_files_on_delete' => false,                // Auto-cleanup
],
```

## Sending Attachments

### Single Attachment

```php
use Ritechoice23\ChatEngine\Enums\AttachmentType;

Chat::message()
    ->from($user)
    ->to($thread)
    ->text('Check this out!')
    ->attach('images/sunset.jpg', AttachmentType::IMAGE, [
        'filename' => 'sunset.jpg',
        'caption' => 'Beautiful view',
    ])
    ->send();
```

### Multiple Attachments

```php
Chat::message()
    ->from($user)
    ->to($thread)
    ->attachments([
        ['path' => 'photos/img1.jpg', 'type' => 'image'],
        ['path' => 'photos/img2.jpg', 'type' => 'image'],
        ['path' => 'docs/report.pdf', 'type' => 'file', 'filename' => 'Q4-Report.pdf'],
    ])
    ->send();
```

### Attachment-Only Messages

```php
// No text, just files
Chat::message()
    ->from($user)
    ->to($thread)
    ->attach('voice/note.mp3', AttachmentType::AUDIO, ['duration' => 30])
    ->send();
```

## View Once (Self-Destruct)

```php
Chat::message()
    ->from($user)
    ->to($thread)
    ->viewOnce('secret/photo.jpg', AttachmentType::IMAGE)
    ->send();
```

Consuming view-once media:

```php
$attachment = $message->attachments->first();

if ($attachment->isAccessible()) {
    $url = $attachment->consume();  // Returns URL and marks as viewed
} else {
    // Already viewed, URL is null
}
```

## Uploading Files from Request

Upload files directly from Laravel's request:

### Single File

```php
Chat::message()
    ->from($request->user())
    ->to($thread)
    ->text('Here is the file you requested')
    ->attachUpload($request->file('attachment'))
    ->send();
```

### Multiple Files

```php
Chat::message()
    ->from($request->user())
    ->to($thread)
    ->attachUploads($request->file('attachments'))
    ->send();
```

### With Options

```php
Chat::message()
    ->from($request->user())
    ->to($thread)
    ->attachUpload($request->file('photo'), AttachmentType::IMAGE, [
        'caption' => 'My vacation photo',
    ])
    ->send();
```

### View-Once Upload

```php
// Self-destructing file upload
Chat::message()
    ->from($request->user())
    ->to($thread)
    ->attachUploadViewOnce($request->file('secret_photo'))
    ->send();
```

## Advanced: Manual Upload

For more control, use `UploadAttachment` directly:

```php
use Ritechoice23\ChatEngine\Actions\UploadAttachment;

$data = app(UploadAttachment::class)->execute(
    file: $request->file('attachment'),
    disk: 's3',           // Override disk
    path: 'custom/path',  // Override path
);

Chat::message()
    ->from($user)
    ->to($thread)
    ->attach($data['path'], $data['type'], $data)
    ->send();
```

## Attachment Options

| Option | Type | Description |
|--------|------|-------------|
| `disk` | string | Storage disk (default: config) |
| `filename` | string | Original filename |
| `mime_type` | string | MIME type |
| `size` | int | File size in bytes |
| `duration` | int | Duration in seconds (video/audio) |
| `width` | int | Width in pixels (image/video) |
| `height` | int | Height in pixels (image/video) |
| `thumbnail_path` | string | Path to thumbnail |
| `blurhash` | string | BlurHash placeholder |
| `caption` | string | Attachment caption |
| `view_once` | bool | Self-destruct after viewing |
| `metadata` | array | Custom data |

## Model Methods

```php
$attachment = $message->attachments->first();

$attachment->url;           // Full URL (generated from disk + path)
$attachment->thumbnail_url; // Thumbnail URL
$attachment->human_size;    // "2.5 MB"
$attachment->human_duration;// "3:45"
$attachment->is_consumed;   // View-once consumed?
$attachment->isAccessible();// Can still be viewed?
$attachment->consume();     // Get URL and mark viewed
$attachment->temporaryUrl(60); // Signed URL for private disks
$attachment->deleteFile();  // Remove from storage
```

## API Resource

Include attachments when fetching messages:

```php
$messages = Message::with('attachments')->where(...)->get();

return MessageResource::collection($messages);
```

Output:

```json
{
    "attachments": [
        {
            "id": 1,
            "type": "image",
            "url": "https://...",
            "filename": "photo.jpg",
            "human_size": "1.2 MB",
            "blurhash": "LEHV6nWB...",
            "view_once": false,
            "is_consumed": false
        }
    ]
}
```

## AttachmentType Enum

```php
use Ritechoice23\ChatEngine\Enums\AttachmentType;

AttachmentType::IMAGE;
AttachmentType::VIDEO;
AttachmentType::AUDIO;
AttachmentType::FILE;

// Helper methods
$type->hasDuration();     // true for video/audio
$type->hasDimensions();   // true for image/video
$type->supportsBlurHash();// true for image/video
$type->mimeTypePrefixes();// ['image/'] for IMAGE
```
