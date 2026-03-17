<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionClass;
use Respect\Rest\Request;
use Respect\Rest\Routable;

use function assert;
use function method_exists;

final class ClassName extends ControllerRoute
{
    protected object|null $instance = null;

    /** @param array<int, mixed> $constructorParams */
    public function __construct(
        string $method,
        string $pattern,
        public string $class = '',
        public array $constructorParams = [],
    ) {
        $this->reflectionTarget = $class;

        parent::__construct($method, $pattern);
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, Request $request): mixed
    {
        if ($this->instance === null) {
            $this->instance = $this->createInstance();
        }

        return $this->invokeTarget($this->instance, $method, $params, $request);
    }

    protected function createInstance(): Routable
    {
        /** @var class-string $className */
        $className = $this->class;
        $reflection = new ReflectionClass($className);

        if (!$reflection->implementsInterface(Routable::class)) {
            throw new InvalidArgumentException(
                'Routed classes must implement Respect\\Rest\\Routable',
            );
        }

        if (
            empty($this->constructorParams)
            || !method_exists($this->class, '__construct')
        ) {
            $instance = $reflection->newInstance();
        } else {
            $instance = $reflection->newInstanceArgs($this->constructorParams);
        }

        assert($instance instanceof Routable);

        return $instance;
    }
}
