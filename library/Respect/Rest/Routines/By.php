<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Generic routine executed before the route */
class By extends AbstractSyncedRoutine implements ProxyableBy
{

    public function by(Request $request, $params)
    {
        return call_user_func_array($this->callback, $params);
    }

}
