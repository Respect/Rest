<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Routine that runs before the route matching */
interface ProxyableWhen
{
    /** Executed to check if the route matchs */
    public function when(Request $request, $params);
}
