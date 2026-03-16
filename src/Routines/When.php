<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Generic routine executed before route matching */
final class When extends AbstractSyncedRoutine implements ProxyableWhen
{
    public function when(Request $request, array $params): mixed
    {
        return $this->execute($request, $params);
    }
}
