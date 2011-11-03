<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Generic routine executed before route matching */
class When extends AbstractSyncedRoutine implements ProxyableWhen
{

    public function when(Request $request, $params)
    {
        return call_user_func_array($this->callback, $params);
    }

}
