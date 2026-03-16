<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Generic routine executed before the route */
class By extends AbstractSyncedRoutine implements ProxyableBy
{
    public function by(Request $request, array $params): mixed
    {
        return $this->execute($request, $params);
    }
}
