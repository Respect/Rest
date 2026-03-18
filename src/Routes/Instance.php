<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routable;

final class Instance extends ControllerRoute
{
    public string $class = '';

    public function __construct(string $method, string $pattern, protected object $instance)
    {
        $this->class = $instance::class;
        $this->reflectionTarget = $instance;

        parent::__construct($method, $pattern);
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, DispatchContext $context): mixed
    {
        if (!$this->instance instanceof Routable) {
            throw new InvalidArgumentException(
                'Route target must be an instance of Respect\Rest\Routable',
            );
        }

        return $this->invokeTarget($this->instance, $method, $params, $context);
    }
}
