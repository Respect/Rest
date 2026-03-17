<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\DispatchContext;

/** Routine that runs before the route */
interface ProxyableBy
{
    /**
     * Executed before the route
     *
     * @param array<int, mixed> $params
     */
    public function by(DispatchContext $context, array $params): mixed;
}
