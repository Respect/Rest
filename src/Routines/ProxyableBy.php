<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Routine that runs before the route */
interface ProxyableBy
{
    /** Executed before the route */
    public function by(Request $request, array $params): mixed;
}
