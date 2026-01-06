<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Actions\CreateThread;
use Ritechoice23\ChatEngine\Actions\SendMessage;
use Ritechoice23\ChatEngine\Resources\MessageResource;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->userA = User::create(['name' => 'User A', 'email' => 'a@test.com']);
    $this->userB = User::create(['name' => 'User B', 'email' => 'b@test.com']);

    $createThread = new CreateThread;
    $this->thread = $createThread->direct($this->userA, $this->userB);

    $sendMessage = new SendMessage;
    $this->message = $sendMessage->text($this->thread, $this->userA, 'Test message');
});

describe('Message Reactions', function () {
    it('can receive reactions', function () {
        // User B reacts to User A's message
        $this->userB->react($this->message, 'like');

        expect($this->message->reactionsCount())->toBe(1)
            ->and($this->message->isReactedBy($this->userB))->toBeTrue()
            ->and($this->message->reactionBy($this->userB)?->reaction_type)->toBe('like');
    });

    it('can receive multiple reaction types', function () {
        $this->userA->react($this->message, 'love');
        $this->userB->react($this->message, 'like');

        expect($this->message->reactionsCount())->toBe(2);

        $breakdown = $this->message->reactionsBreakdown();
        expect($breakdown)->toHaveKey('love')
            ->and($breakdown)->toHaveKey('like')
            ->and($breakdown['love'])->toBe(1)
            ->and($breakdown['like'])->toBe(1);
    });

    it('can change reaction', function () {
        // Initially react with like
        $this->userB->react($this->message, 'like');
        expect($this->message->reactionBy($this->userB)?->reaction_type)->toBe('like');

        // Change to love
        $this->userB->react($this->message, 'love');

        // Refresh to get latest
        $this->message->refresh();

        expect($this->message->reactionsCount())->toBe(1)
            ->and($this->message->reactionBy($this->userB)?->reaction_type)->toBe('love');
    });

    it('can remove reaction', function () {
        $this->userB->react($this->message, 'like');
        expect($this->message->isReactedBy($this->userB))->toBeTrue();

        $this->userB->unreact($this->message);
        $this->message->refresh();

        expect($this->message->isReactedBy($this->userB))->toBeFalse()
            ->and($this->message->reactionsCount())->toBe(0);
    });

    it('supports emoji reactions', function () {
        $this->userA->react($this->message, '🔥');
        $this->userB->react($this->message, '❤️');

        expect($this->message->reactionsCount())->toBe(2);

        $breakdown = $this->message->reactionsBreakdown();
        expect($breakdown)->toHaveKey('🔥')
            ->and($breakdown)->toHaveKey('❤️');
    });
});

describe('CanChat Trait with Reactions', function () {
    it('allows actors to react to messages', function () {
        $reaction = $this->userB->react($this->message, 'celebrate');

        expect($reaction)->not->toBeNull()
            ->and($this->userB->hasReactedTo($this->message))->toBeTrue()
            ->and($this->userB->reactionTo($this->message))->toBe('celebrate');
    });

    it('tracks reactions given by actor', function () {
        $sendMessage = new SendMessage;
        $message2 = $sendMessage->text($this->thread, $this->userA, 'Another message');

        $this->userB->react($this->message, 'like');
        $this->userB->react($message2, 'love');

        $reactionsGiven = $this->userB->reactionsGiven;

        expect($reactionsGiven)->toHaveCount(2);
    });
});

describe('MessageResource with Reactions', function () {
    it('includes reactions data in resource', function () {
        $this->userA->react($this->message, 'like');
        $this->userB->react($this->message, 'love');

        $resource = new MessageResource($this->message);
        $array = $resource->resolve(request());

        expect($array)->toHaveKey('reactions')
            ->and($array['reactions']['count'])->toBe(2)
            ->and($array['reactions']['breakdown'])->toHaveKey('like')
            ->and($array['reactions']['breakdown'])->toHaveKey('love');
    });

    it('includes user reaction status for viewing actor', function () {
        $this->userB->react($this->message, 'love');
        MessageResource::viewAs($this->userB);

        $resource = new MessageResource($this->message);
        $array = $resource->resolve(request());

        expect($array['reactions']['has_reacted'])->toBeTrue()
            ->and($array['reactions']['user_reaction'])->toBe('love');

        MessageResource::viewAs(null);
    });

    it('shows null user reaction when actor has not reacted', function () {
        MessageResource::viewAs($this->userB);

        $resource = new MessageResource($this->message);
        $array = $resource->resolve(request());

        expect($array['reactions']['has_reacted'])->toBeFalse()
            ->and($array['reactions']['user_reaction'])->toBeNull();

        MessageResource::viewAs(null);
    });
});
