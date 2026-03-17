<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use Respect\Rest\Request;

final class Error extends Callback
{
    /** @var callable */
    public $callback;

    /** @var array<int, array<int, mixed>> */
    public array $errors = [];

    public function __construct(callable $callback)
    {
        parent::__construct('ANY', '^$', $callback);
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, Request $request): mixed
    {
        return ($this->callback)($this->errors);
    }
}
