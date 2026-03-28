<?php

declare(strict_types=1);

namespace Respect\Rest\Handlers;

use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routes\Callback;

final class ErrorHandler extends Callback
{
    /** @var array<int, array<int, mixed>> */
    private array $errors = [];

    public function __construct(NamespaceLookup $routineLookup, callable $callback)
    {
        parent::__construct($routineLookup, 'ANY', '^$', $callback);
    }

    public function addError(int $errno, string $errstr, string $errfile, int $errline): void
    {
        $this->errors[] = [$errno, $errstr, $errfile, $errline];
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    public function clearErrors(): void
    {
        $this->errors = [];
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, DispatchContext $context): mixed
    {
        return ($this->callback)($this->errors);
    }
}
