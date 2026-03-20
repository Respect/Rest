<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

final class Status extends Callback
{
    /** @var callable */
    public $callback;

    public function __construct(public readonly int|null $statusCode, callable $callback)
    {
        parent::__construct('ANY', '^$', $callback);
    }
}
