<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Generic routine executed before route matching */
class When extends AbstractSyncedRoutine implements ProxyableWhen
{

    public function when(Request $request, $params)
    {
        $valid = $this->execute($request, $params);

        if (!$valid)
            header('HTTP/1.1 400');

        return $valid;
    }

}
