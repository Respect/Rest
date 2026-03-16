<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Routine that runs before the route matching */
interface ProxyableWhen
{
    /** Executed to check if the route matches */
    public function when(Request $request, array $params): mixed;
}
