<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\DispatchContext;

/** Generic routine executed after the route */
final class Through extends AbstractSyncedRoutine implements ProxyableThrough
{
    /** @param array<int, mixed> $params */
    // phpcs:ignore Generic.NamingConventions.ConstructorName.OldStyle
    public function through(DispatchContext $context, array $params): mixed
    {
        return $this->execute($context, $params);
    }
}
