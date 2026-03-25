<?php

declare(strict_types=1);

namespace Respect\Rest\Handlers;

use Respect\Rest\Routes\Callback;

final class StatusHandler extends Callback
{
    /** @var callable */
    public $callback;

    public function __construct(public readonly int|null $statusCode, callable $callback)
    {
        parent::__construct('ANY', '^$', $callback);
    }
}
