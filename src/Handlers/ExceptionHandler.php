<?php

declare(strict_types=1);

namespace Respect\Rest\Handlers;

use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routes\Callback;
use Throwable;

use function is_a;

final class ExceptionHandler extends Callback
{
    private Throwable|null $exception = null;

    public function __construct(NamespaceLookup $routineLookup, public private(set) string $class, callable $callback)
    {
        parent::__construct($routineLookup, 'ANY', '^$', $callback);
    }

    public function matches(Throwable $e): bool
    {
        return is_a($e, $this->class);
    }

    public function capture(Throwable $e): void
    {
        $this->exception = $e;
    }

    public function clearException(): void
    {
        $this->exception = null;
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, DispatchContext $context): mixed
    {
        return ($this->callback)($this->exception);
    }
}
