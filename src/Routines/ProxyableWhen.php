<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\DispatchContext;

/** Routine that runs before the route matching */
interface ProxyableWhen
{
    /**
     * Executed to check if the route matches
     *
     * @param array<int, mixed> $params
     */
    public function when(DispatchContext $context, array $params): mixed;
}
