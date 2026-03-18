<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\DispatchContext;

/** Generic routine executed before route matching */
final class When extends AbstractSyncedRoutine implements ProxyableWhen
{
    /** @param array<int, mixed> $params */
    // phpcs:ignore Generic.NamingConventions.ConstructorName.OldStyle
    public function when(DispatchContext $context, array $params): mixed
    {
        return $this->execute($context, $params);
    }
}
