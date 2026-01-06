<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Actions\CreateThread;
use Ritechoice23\ChatEngine\Actions\SendMessage;
use Ritechoice23\ChatEngine\Models\Message;
use Ritechoice23\ChatEngine\Pipes\DetectMentions;
use Ritechoice23\ChatEngine\Pipes\DetectUrls;
use Ritechoice23\ChatEngine\Pipes\FilterProfanity;
use Ritechoice23\ChatEngine\Pipes\SanitizeContent;
use Ritechoice23\ChatEngine\Pipes\ValidateMediaUrls;
use Ritechoice23\ChatEngine\Support\MessagePipeline;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);

    $createThread = new CreateThread;
    $this->thread = $createThread->direct($this->userA, $this->userB);
});

describe('DetectMentions Pipe', function () {
    it('detects markdown-style mentions', function () {
        $message = new Message([
            'payload' => ['content' => 'Hello @[John Doe](123), how are you?'],
        ]);

        $pipe = new DetectMentions;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['mentions'])->toHaveCount(1)
            ->and($result->payload['mentions'][0]['name'])->toBe('John Doe')
            ->and($result->payload['mentions'][0]['id'])->toBe(123);
    });

    it('detects simple username mentions', function () {
        $message = new Message([
            'payload' => ['content' => 'Hey @johndoe, check this out!'],
        ]);

        $pipe = new DetectMentions;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['mentions'])->toHaveCount(1)
            ->and($result->payload['mentions'][0]['username'])->toBe('johndoe');
    });

    it('detects multiple mentions', function () {
        $message = new Message([
            'payload' => ['content' => 'Hello @[User One](1) and @[User Two](2)!'],
        ]);

        $pipe = new DetectMentions;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['mentions'])->toHaveCount(2);
    });

    it('skips non-text messages', function () {
        $message = new Message([
            'payload' => ['url' => 'https://example.com/image.jpg'],
        ]);

        $pipe = new DetectMentions;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload)->not->toHaveKey('mentions');
    });
});

describe('DetectUrls Pipe', function () {
    it('detects URLs in content', function () {
        $message = new Message([
            'payload' => ['content' => 'Check out https://example.com and http://test.org'],
        ]);

        $pipe = new DetectUrls;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['urls'])->toHaveCount(2)
            ->and($result->payload['urls'][0]['domain'])->toBe('example.com')
            ->and($result->payload['urls'][1]['domain'])->toBe('test.org');
    });

    it('skips messages without URLs', function () {
        $message = new Message([
            'payload' => ['content' => 'Just plain text without any links'],
        ]);

        $pipe = new DetectUrls;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload)->not->toHaveKey('urls');
    });
});

describe('SanitizeContent Pipe', function () {
    it('removes dangerous HTML tags', function () {
        $message = new Message([
            'payload' => ['content' => 'Hello <script>alert("xss")</script> world'],
        ]);

        $pipe = new SanitizeContent;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['content'])->not->toContain('<script>')
            ->and($result->payload['content'])->toContain('Hello')
            ->and($result->payload['content'])->toContain('world');
    });

    it('sanitizes captions in media messages', function () {
        $message = new Message([
            'payload' => [
                'url' => 'https://example.com/image.jpg',
                'caption' => '<script>alert("xss")</script>Nice photo',
            ],
        ]);

        $pipe = new SanitizeContent;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['caption'])->not->toContain('<script>');
    });
});

describe('ValidateMediaUrls Pipe', function () {
    it('validates media URLs', function () {
        $message = new Message([
            'payload' => ['url' => 'https://example.com/image.jpg'],
        ]);

        $pipe = new ValidateMediaUrls;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result)->toBeInstanceOf(Message::class);
    });

    it('throws exception for invalid URLs', function () {
        $message = new Message([
            'payload' => ['url' => 'not-a-valid-url'],
        ]);

        $pipe = new ValidateMediaUrls;

        $pipe->handle($message, fn ($msg) => $msg);
    })->throws(InvalidArgumentException::class);

    it('throws exception for non-http protocols', function () {
        $message = new Message([
            'payload' => ['url' => 'ftp://example.com/file.jpg'],
        ]);

        $pipe = new ValidateMediaUrls;

        $pipe->handle($message, fn ($msg) => $msg);
    })->throws(InvalidArgumentException::class);
});

describe('FilterProfanity Pipe', function () {
    it('does not filter when list is empty', function () {
        $message = new Message([
            'payload' => ['content' => 'This is clean content'],
        ]);

        $pipe = new FilterProfanity;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['content'])->toBe('This is clean content');
    });

    it('filters profanity using config', function () {
        config()->set('chat-engine.profanity.words', ['badword', 'offensive']);

        $message = new Message([
            'payload' => ['content' => 'This is a badword and offensive content'],
        ]);

        $pipe = new FilterProfanity;
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['content'])->toContain('*******')
            ->and($result->payload['content'])->toContain('*********')
            ->and($result->payload['content'])->not->toContain('badword')
            ->and($result->payload['content'])->not->toContain('offensive');
    });

    it('filters profanity using on-the-fly list', function () {
        $message = new Message([
            'payload' => ['content' => 'This contains spam'],
        ]);

        $pipe = new FilterProfanity(['spam']);
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['content'])->toContain('****')
            ->and($result->payload['content'])->not->toContain('spam');
    });

    it('can add words on the fly', function () {
        $message = new Message([
            'payload' => ['content' => 'This has bad and ugly words'],
        ]);

        $pipe = new FilterProfanity(['bad']);
        $pipe->addWords(['ugly']);

        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['content'])->toContain('***')
            ->and($result->payload['content'])->toContain('****');
    });

    it('supports remove mode', function () {
        $message = new Message([
            'payload' => ['content' => 'Remove this badword please'],
        ]);

        $pipe = new FilterProfanity(['badword'], null, 'remove');
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['content'])->toBe('Remove this  please')
            ->and($result->payload['content'])->not->toContain('badword');
    });

    it('supports reject mode', function () {
        $message = new Message([
            'payload' => ['content' => 'This has badword in it'],
        ]);

        $pipe = new FilterProfanity(['badword'], null, 'reject');

        expect(fn () => $pipe->handle($message, fn ($msg) => $msg))
            ->toThrow(InvalidArgumentException::class, 'inappropriate content');
    });

    it('allows changing replacement character', function () {
        $message = new Message([
            'payload' => ['content' => 'Filter this badword now'],
        ]);

        $pipe = new FilterProfanity(['badword'], '#');
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['content'])->toContain('#######')
            ->and($result->payload['content'])->not->toContain('*');
    });

    it('can set mode on the fly', function () {
        $message = new Message([
            'payload' => ['content' => 'Test badword here'],
        ]);

        $pipe = new FilterProfanity(['badword']);
        $pipe->setMode('remove');

        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['content'])->toBe('Test  here');
    });

    it('is case insensitive', function () {
        $message = new Message([
            'payload' => ['content' => 'This has BADWORD and BadWord'],
        ]);

        $pipe = new FilterProfanity(['badword']);
        $result = $pipe->handle($message, fn ($msg) => $msg);

        expect($result->payload['content'])->toContain('*******')
            ->and($result->payload['content'])->not->toContain('BADWORD')
            ->and($result->payload['content'])->not->toContain('BadWord');
    });
});

describe('MessagePipeline', function () {
    it('processes message through configured pipes', function () {
        $sendMessage = new SendMessage;
        $message = $sendMessage->text($this->thread, $this->userA, 'Hello @[User](1)!');

        // Configure pipeline
        config()->set('chat-engine.pipelines.message', [
            DetectMentions::class,
        ]);

        $pipeline = new MessagePipeline;
        $processed = $pipeline->process($message);

        expect($processed->payload)->toHaveKey('mentions');
    });

    it('processes through multiple pipes in order', function () {
        $sendMessage = new SendMessage;
        $message = $sendMessage->text(
            $this->thread,
            $this->userA,
            'Check @[User](1) at https://example.com'
        );

        // Configure multiple pipes
        config()->set('chat-engine.pipelines.message', [
            DetectMentions::class,
            DetectUrls::class,
        ]);

        $pipeline = new MessagePipeline;
        $processed = $pipeline->process($message);

        expect($processed->payload)->toHaveKey('mentions')
            ->and($processed->payload)->toHaveKey('urls');
    });
});
