<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Builders;

use Illuminate\Database\Eloquent\Model;
use Ritechoice23\ChatEngine\Enums\ParticipantRole;
use Ritechoice23\ChatEngine\Enums\ThreadType;
use Ritechoice23\ChatEngine\Events\ParticipantAdded;
use Ritechoice23\ChatEngine\Events\ThreadCreated;
use Ritechoice23\ChatEngine\Models\Thread;
use Ritechoice23\ChatEngine\Support\ThreadHasher;

class ThreadBuilder
{
    protected ThreadType $type = ThreadType::GROUP;

    protected ?string $name = null;

    /** @var array<array{actor: Model, role: ParticipantRole}> */
    protected array $participants = [];

    protected array $metadata = [];

    protected bool $findExisting = true;

    /**
     * Create a direct thread between exactly two actors.
     */
    public function between(Model $actorA, Model $actorB): self
    {
        $this->type = ThreadType::DIRECT;
        $this->participants = [
            ['actor' => $actorA, 'role' => ParticipantRole::MEMBER],
            ['actor' => $actorB, 'role' => ParticipantRole::MEMBER],
        ];

        return $this;
    }

    /**
     * Create a group thread.
     */
    public function group(?string $name = null): self
    {
        $this->type = ThreadType::GROUP;
        $this->name = $name;

        return $this;
    }

    /**
     * Create a channel thread.
     */
    public function channel(string $name): self
    {
        $this->type = ThreadType::CHANNEL;
        $this->name = $name;

        return $this;
    }

    /**
     * Create a broadcast thread.
     */
    public function broadcast(string $name): self
    {
        $this->type = ThreadType::BROADCAST;
        $this->name = $name;

        return $this;
    }

    /**
     * Set custom thread type.
     */
    public function type(ThreadType|string $type): self
    {
        $this->type = $type instanceof ThreadType
            ? $type
            : ThreadType::from($type);

        return $this;
    }

    /**
     * Set thread name.
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Add participants to the thread.
     *
     * @param  Model|iterable<Model>  $actors
     */
    public function participants(Model|iterable $actors, ParticipantRole $role = ParticipantRole::MEMBER): self
    {
        $actors = $actors instanceof Model ? [$actors] : $actors;

        foreach ($actors as $actor) {
            $this->participants[] = [
                'actor' => $actor,
                'role' => $role,
            ];
        }

        return $this;
    }

    /**
     * Add a participant as owner.
     */
    public function withOwner(Model $actor): self
    {
        return $this->participants($actor, ParticipantRole::OWNER);
    }

    /**
     * Add a participant as admin.
     */
    public function withAdmin(Model $actor): self
    {
        return $this->participants($actor, ParticipantRole::ADMIN);
    }

    /**
     * Add a participant as member.
     */
    public function withMember(Model $actor): self
    {
        return $this->participants($actor, ParticipantRole::MEMBER);
    }

    /**
     * Set thread metadata.
     */
    public function metadata(array $metadata): self
    {
        $this->metadata = array_merge($this->metadata, $metadata);

        return $this;
    }

    /**
     * Always create a new thread, never find existing.
     */
    public function alwaysNew(): self
    {
        $this->findExisting = false;

        return $this;
    }

    /**
     * Create or find the thread.
     */
    public function create(): Thread
    {
        $this->validateParticipants();

        $hash = null;

        // Only generate hash if we're looking for existing threads
        if ($this->findExisting && $this->shouldGenerateHash()) {
            $hash = $this->generateHash();

            if (! config('chat-engine.threads.allow_duplicates', false)) {
                $existing = $this->findByHash($hash);
                if ($existing) {
                    return $existing;
                }
            }
        }

        $threadModel = config('chat-engine.models.thread', Thread::class);

        $thread = $threadModel::create([
            'type' => $this->type->value,
            'name' => $this->name,
            'hash' => $hash,
            'metadata' => $this->metadata ?: null,
        ]);

        $this->addParticipants($thread);

        ThreadCreated::dispatch($thread);

        return $thread;
    }

    /**
     * Find an existing thread by hash or return null.
     */
    public function find(): ?Thread
    {
        $hash = $this->generateHash();

        return $this->findByHash($hash);
    }

    /**
     * Create or find the thread.
     */
    public function firstOrCreate(): Thread
    {
        return $this->find() ?? $this->alwaysNew()->create();
    }

    protected function validateParticipants(): void
    {
        if (empty($this->participants)) {
            throw new \InvalidArgumentException('Thread must have at least one participant.');
        }

        if ($this->type === ThreadType::DIRECT && count($this->participants) !== 2) {
            throw new \InvalidArgumentException('Direct threads must have exactly 2 participants.');
        }
    }

    protected function shouldGenerateHash(): bool
    {
        return config('chat-engine.threads.hash_participants', true);
    }

    protected function generateHash(): string
    {
        $includeRoles = config('chat-engine.threads.include_roles_in_hash', true);

        return ThreadHasher::generate(
            collect($this->participants),
            $includeRoles,
            $this->type
        );
    }

    protected function findByHash(string $hash): ?Thread
    {
        $threadModel = config('chat-engine.models.thread', Thread::class);

        return $threadModel::byHash($hash)->first();
    }

    protected function addParticipants(Thread $thread): void
    {
        foreach ($this->participants as $participant) {
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
