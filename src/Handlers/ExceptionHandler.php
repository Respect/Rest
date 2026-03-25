<?php

declare(strict_types=1);

namespace Respect\Rest\Handlers;

use Respect\Rest\DispatchContext;
use Respect\Rest\Routes\Callback;
use Throwable;

final class ExceptionHandler extends Callback
{
    /** @var callable */
    public $callback;

    public Throwable|null $exception = null;

    public function __construct(public string $class, callable $callback)
    {
        parent::__construct('ANY', '^$', $callback);
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, DispatchContext $context): mixed
    {
        return ($this->callback)($this->exception);
    }
}
