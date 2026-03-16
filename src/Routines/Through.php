<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Generic routine executed after the route */
class Through extends AbstractSyncedRoutine implements ProxyableThrough
{
    public function through(Request $request, array $params): mixed
    {
        return $this->execute($request, $params);
    }
}
