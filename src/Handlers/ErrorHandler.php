<?php

declare(strict_types=1);

namespace Respect\Rest\Handlers;

use Respect\Rest\DispatchContext;
use Respect\Rest\Routes\Callback;

final class ErrorHandler extends Callback
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
    public function runTarget(string $method, array &$params, DispatchContext $context): mixed
    {
        return ($this->callback)($this->errors);
    }
}
