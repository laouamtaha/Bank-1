<?php

declare(strict_types=1);

namespace Ritechoice23\ChatEngine\Support;

use Illuminate\Database\Eloquent\Model;

class PolicyChecker
{
    /**
     * Check if an actor can perform an action on a resource.
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function authorize(string $ability, Model $actor, mixed $resource): void
    {
        if (! $this->check($ability, $actor, $resource)) {
            throw new \Illuminate\Auth\Access\AuthorizationException(
                "Not authorized to {$ability} this resource."
            );
        }
    }

    /**
     * Check if an actor can perform an action on a resource.
     */
    public function check(string $ability, Model $actor, mixed $resource): bool
    {
        $policy = $this->resolvePolicy($resource);

        if (! $policy) {
            return false;
        }

        if (! method_exists($policy, $ability)) {
            return false;
        }

        return $policy->{$ability}($actor, $resource);
    }

    /**
     * Resolve the policy for a given resource.
     */
    protected function resolvePolicy(mixed $resource): ?object
    {
        $policyMap = [
            \Ritechoice23\ChatEngine\Models\Thread::class => \Ritechoice23\ChatEngine\Policies\ThreadPolicy::class,
            \Ritechoice23\ChatEngine\Models\Message::class => \Ritechoice23\ChatEngine\Policies\MessagePolicy::class,
        ];

        $class = get_class($resource);

        if (! isset($policyMap[$class])) {
            return null;
        }

        return new $policyMap[$class];
    }
}
