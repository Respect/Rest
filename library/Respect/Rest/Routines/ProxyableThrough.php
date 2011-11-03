<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Routine that runs after the route */
interface ProxyableThrough
{
    /** Executed after the route */
    public function through(Request $request, $params);
}
