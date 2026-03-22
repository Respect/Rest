<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use ReflectionFunctionAbstract;
use Respect\Parameter\Resolver;
use Respect\Rest\DispatchContext;

/** Generic routine executed before the route */
final class By extends AbstractRoutine implements ProxyableBy
{
    private ReflectionFunctionAbstract|null $reflection = null;

    /** @param array<int, mixed> $params */
    // phpcs:ignore Generic.NamingConventions.ConstructorName.OldStyle
    public function by(DispatchContext $context, array $params): mixed
    {
        $this->reflection ??= Resolver::reflectCallable($this->getCallback());
        $args = $context->resolver()->resolve($this->reflection, $params);

        return ($this->getCallback())(...$args);
    }
}
