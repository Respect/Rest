<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use Respect\Rest\Request;

class Exception extends Callback
{
    public string $class;

    /** @var callable */
    public $callback;

    public ?\Throwable $exception = null;

    public function __construct(string $class, callable $callback)
    {
        $this->class = $class;
        parent::__construct('ANY', '^$', $callback);
    }

    public function runTarget(string $method, array &$params, Request $request): mixed
    {
        return ($this->callback)($this->exception);
    }
}
