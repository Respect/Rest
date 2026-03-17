<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Routine that runs after the route */
interface ProxyableThrough
{
    /**
     * Executed after the route
     *
     * @param array<int, mixed> $params
     */
    public function through(Request $request, array $params): mixed;
}
