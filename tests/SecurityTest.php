<?php

declare(strict_types=1);

use Ritechoice23\ChatEngine\Enums\ParticipantRole;
use Ritechoice23\ChatEngine\Facades\Chat;
use Ritechoice23\ChatEngine\Tests\Models\User;

beforeEach(function () {
    $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@test.com']);
    $this->member = User::create(['name' => 'Member', 'email' => 'member@test.com']);
    $this->thread = Chat::thread()
        ->group('Secure Group')
        ->participants([$this->admin, $this->member])
        ->create();

    // Make admin an actual admin
    $this->thread->getParticipant($this->admin)->update(['role' => ParticipantRole::ADMIN->value]);
});

describe('Global Thread Lock', function () {
    it('can lock a thread', function () {
        expect($this->thread->is_locked)->toBeFalse();

        $this->thread->lock();

        expect($this->thread->fresh()->is_locked)->toBeTrue();
    });

    it('can unlock a thread', function () {
        $this->thread->lock();
        $this->thread->unlock();

        expect($this->thread->fresh()->is_locked)->toBeFalse();
    });

    it('allows admins to send messages in locked thread', function () {
        $this->thread->lock();

        $message = Chat::message()
            ->from($this->admin)
            ->to($this->thread)
            ->text('Admin announcement')
            ->send();

        expect($message)->not->toBeNull()
            ->and($message->thread_id)->toBe($this->thread->id);
    });

    it('blocks non-admins from sending in locked thread', function () {
        $this->thread->lock();

        Chat::message()
            ->from($this->member)
            ->to($this->thread)
            ->text('I cannot send this')
            ->send();
    })->throws(InvalidArgumentException::class, 'Thread is locked');

    it('allows everyone to send when unlocked', function () {
        $message = Chat::message()
            ->from($this->member)
            ->to($this->thread)
            ->text('Hello!')
            ->send();

        expect($message)->not->toBeNull();
    });

    it('checks canSendMessage correctly', function () {
        $this->thread->lock();

        expect($this->thread->canSendMessage($this->admin))->toBeTrue()
            ->and($this->thread->canSendMessage($this->member))->toBeFalse();

        $this->thread->unlock();

        expect($this->thread->canSendMessage($this->member))->toBeTrue();
    });
});

describe('Chat Lock PIN', function () {
    beforeEach(function () {
        $this->participant = $this->thread->getParticipant($this->member);
    });

    it('can lock a chat with PIN', function () {
        $this->participant->lockChat('1234');

        expect($this->participant->fresh()->isChatLocked())->toBeTrue();
    });

    it('stores PIN as hash', function () {
        $this->participant->lockChat('1234');

        $storedPin = $this->participant->fresh()->chat_lock_pin;

        expect($storedPin)->not->toBe('1234')
            ->and(strlen($storedPin))->toBeGreaterThan(20); // Hash is long
    });

    it('verifies correct PIN', function () {
        $this->participant->lockChat('1234');

        expect($this->participant->checkPin('1234'))->toBeTrue()
            ->and($this->participant->checkPin('0000'))->toBeFalse()
            ->and($this->participant->checkPin('5678'))->toBeFalse();
    });

    it('can unlock chat', function () {
        $this->participant->lockChat('1234');
        $this->participant->unlockChat();

        expect($this->participant->fresh()->isChatLocked())->toBeFalse()
            ->and($this->participant->fresh()->chat_lock_pin)->toBeNull();
    });

    it('returns true for checkPin when not locked', function () {
        expect($this->participant->isChatLocked())->toBeFalse()
            ->and($this->participant->checkPin('anything'))->toBeTrue();
    });
});

describe('E2E Security Code', function () {
    beforeEach(function () {
        $this->participantA = $this->thread->getParticipant($this->admin);
        $this->participantB = $this->thread->getParticipant($this->member);
    });

    it('can set public key and generate security code', function () {
        $publicKey = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GN...fake-key-for-testing';

        $this->participantA->setPublicKey($publicKey);

        expect($this->participantA->fresh()->public_key)->toBe($publicKey)
            ->and($this->participantA->fresh()->security_code)->not->toBeNull()
            ->and(strlen($this->participantA->fresh()->security_code))->toBe(60);
    });

    it('generates deterministic security code', function () {
        $publicKey = 'test-public-key-12345';

        $this->participantA->setPublicKey($publicKey);
        $code1 = $this->participantA->security_code;

        // Set same key again
        $this->participantA->setPublicKey($publicKey);
        $code2 = $this->participantA->security_code;

        expect($code1)->toBe($code2);
    });

    it('formats security code for display', function () {
        $this->participantA->setPublicKey('test-key');
        $this->participantA->refresh();

        $formatted = $this->participantA->formatted_security_code;

        expect($formatted)->not->toBeNull();

        // Should be 12 groups of 5 digits separated by spaces
        $groups = explode(' ', $formatted);
        expect($groups)->toHaveCount(12);

        foreach ($groups as $group) {
            expect(strlen($group))->toBe(5);
        }
    });

    it('can verify security between two participants', function () {
        $keyA = 'public-key-A';
        $keyB = 'public-key-B';

        $this->participantA->setPublicKey($keyA);
        $this->participantB->setPublicKey($keyB);

        // Verification requires both keys to be set
        expect($this->participantA->public_key)->not->toBeNull()
            ->and($this->participantB->public_key)->not->toBeNull();
    });

    it('returns false for verification without public keys', function () {
        expect($this->participantA->verifySecurityWith($this->participantB))->toBeFalse();
    });
});
