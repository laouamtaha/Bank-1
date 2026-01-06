<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Actions;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Enums\ParticipantRole;
use Ritechoice23\ChatEngine\Enums\ThreadType;
use Ritechoice23\ChatEngine\Events\ParticipantAdded;
use Ritechoice23\ChatEngine\Events\ThreadCreated;
use Ritechoice23\ChatEngine\Models\Thread;
use Ritechoice23\ChatEngine\Support\ThreadHasher;

class CreateThread
{
    /**
     * Create a new thread with participants.
     *
     * @param  array<array{actor: Model, role: ParticipantRole}>  $participants
     */
    public function execute(
        ThreadType $type,
        array $participants,
        ?string $name = null,
        array $metadata = [],
        bool $findExisting = true,
    ): Thread {
        $this->validateParticipants($type, $participants);

        $hash = null;

        if ($findExisting && $this->shouldGenerateHash()) {
            $hash = $this->generateHash($participants, $type);

            if (! config('chat-engine.threads.allow_duplicates', false)) {
                $existing = $this->findByHash($hash);
                if ($existing) {
                    return $existing;
                }
            }
        }

        $threadModel = config('chat-engine.models.thread', Thread::class);

        $thread = $threadModel::create([
            'type' => $type->value,
            'name' => $name,
            'hash' => $hash,
            'metadata' => $metadata ?: null,
        ]);

        $this->addParticipants($thread, $participants);

        ThreadCreated::dispatch($thread);

        return $thread;
    }

    /**
     * Create a direct thread between two actors.
     */
    public function direct(Model $actorA, Model $actorB): Thread
    {
        return $this->execute(
            type: ThreadType::DIRECT,
            participants: [
                ['actor' => $actorA, 'role' => ParticipantRole::MEMBER],
                ['actor' => $actorB, 'role' => ParticipantRole::MEMBER],
            ],
        );
    }

    /**
     * @param  array<array{actor: Model, role: ParticipantRole}>  $participants
     */
    protected function validateParticipants(ThreadType $type, array $participants): void
    {
        if (empty($participants)) {
            throw new \InvalidArgumentException('Thread must have at least one participant.');
        }

        if ($type === ThreadType::DIRECT && count($participants) !== 2) {
            throw new \InvalidArgumentException('Direct threads must have exactly 2 participants.');
        }
    }

    protected function shouldGenerateHash(): bool
    {
        return config('chat-engine.threads.hash_participants', true);
    }

    /**
     * @param  array<array{actor: Model, role: ParticipantRole}>  $participants
     */
    protected function generateHash(array $participants, ThreadType $type): string
    {
        $includeRoles = config('chat-engine.threads.include_roles_in_hash', true);

        return ThreadHasher::generate(
            collect($participants),
            $includeRoles,
            $type
        );
    }

    protected function findByHash(string $hash): ?Thread
    {
        $threadModel = config('chat-engine.models.thread', Thread::class);

        return $threadModel::byHash($hash)->first();
    }

    /**
     * @param  array<array{actor: Model, role: ParticipantRole}>  $participants
     */
    protected function addParticipants(Thread $thread, array $participants): void
    {
        foreach ($participants as $participant) {
            $threadParticipant = $thread->participants()->create([
                'actor_type' => $participant['actor']->getMorphClass(),
                'actor_id' => $participant['actor']->getKey(),
                'role' => $participant['role']->value,
            ]);

            ParticipantAdded::dispatch(
                $thread,
                $participant['actor'],
                $threadParticipant,
                $participant['role']
            );
        }
    }
}
