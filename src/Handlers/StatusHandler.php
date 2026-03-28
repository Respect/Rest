<?php

declare(strict_types=1);

namespace Respect\Rest\Handlers;

use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Rest\Routes\Callback;

final class StatusHandler extends Callback
{
    public function __construct(
        NamespaceLookup $routineLookup,
        public readonly int|null $statusCode,
        callable $callback,
    ) {
        parent::__construct($routineLookup, 'ANY', '^$', $callback);
    }
}
