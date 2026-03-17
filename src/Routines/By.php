<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Generic routine executed before the route */
final class By extends AbstractSyncedRoutine implements ProxyableBy
{
    /** @param array<int, mixed> $params */
    // phpcs:ignore Generic.NamingConventions.ConstructorName.OldStyle
    public function by(Request $request, array $params): mixed
    {
        return $this->execute($request, $params);
    }
}
