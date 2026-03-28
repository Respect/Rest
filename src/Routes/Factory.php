<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routable;

final class Factory extends ControllerRoute
{
    protected object|null $instance = null;

    public function __construct(
        NamespaceLookup $routineLookup,
        string $method,
        string $pattern,
        public private(set) string $class,
        /** @var callable */
        protected $factory,
    ) {
        $this->reflectionTarget = $class;

        parent::__construct($routineLookup, $method, $pattern);
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, DispatchContext $context): mixed
    {
        if ($this->instance === null) {
            $this->instance = ($this->factory)($method, $params);
        }

        if (!$this->instance instanceof Routable) {
            throw new InvalidArgumentException(
                'Routed classes must implement the Respect\\Rest\\Routable interface',
            );
        }

        return $this->invokeTarget($this->instance, $method, $params, $context);
    }
}
