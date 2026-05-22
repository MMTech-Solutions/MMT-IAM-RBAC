<?php

declare(strict_types=1);

namespace Mmtech\Rbac\Tests\Support;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;

final class StubGate implements GateContract
{
    public function __construct(
        private readonly bool $allowsResult,
        private readonly ?string $abilityFilter = null,
    ) {}

    public function has($ability): bool
    {
        return false;
    }

    public function define($ability, $callback): static
    {
        return $this;
    }

    public function resource($name, $class, ?array $abilities = null): static
    {
        return $this;
    }

    public function policy($class, $policy): static
    {
        return $this;
    }

    public function before(callable $callback): static
    {
        return $this;
    }

    public function after(callable $callback): static
    {
        return $this;
    }

    public function allows($ability, $arguments = []): bool
    {
        if ($this->abilityFilter !== null && $ability !== $this->abilityFilter) {
            return false;
        }

        return $this->allowsResult;
    }

    public function denies($ability, $arguments = []): bool
    {
        return ! $this->allows($ability, $arguments);
    }

    public function check($abilities, $arguments = []): bool
    {
        return $this->allows($abilities, $arguments);
    }

    public function any($abilities, $arguments = []): bool
    {
        return $this->allows($abilities, $arguments);
    }

    public function authorize($ability, $arguments = []): void {}

    public function inspect($ability, $arguments = []): Response
    {
        return $this->allows($ability, $arguments)
            ? Response::allow()
            : Response::deny();
    }

    public function raw($ability, $arguments = [])
    {
        return $this->allows($ability, $arguments);
    }

    public function getPolicyFor($class): mixed
    {
        return null;
    }

    public function forUser($user): static
    {
        return $this;
    }

    public function abilities(): array
    {
        return [];
    }
}
