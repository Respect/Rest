<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Generic routine executed after the route */
class Through extends AbstractSyncedRoutine implements ProxyableThrough
{

    public function through(Request $request, $params)
    {
        return call_user_func_array($this->callback, $params);
    }

}

