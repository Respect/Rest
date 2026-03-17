<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use Respect\Rest\Request;
use Throwable;

final class Exception extends Callback
{
    /** @var callable */
    public $callback;

    public Throwable|null $exception = null;

    public function __construct(public string $class, callable $callback)
    {
        parent::__construct('ANY', '^$', $callback);
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, Request $request): mixed
    {
        return ($this->callback)($this->exception);
    }
}
