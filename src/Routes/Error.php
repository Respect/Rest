<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

class Error extends Callback
{
    /** @var callable */
    public $callback;

    /** @var array<int, array<int, mixed>> */
    public array $errors = [];

    public function __construct(callable $callback)
    {
        parent::__construct('ANY', '^$', $callback);
    }

    public function runTarget(string $method, array &$params): mixed
    {
        return ($this->callback)($this->errors);
    }
}
