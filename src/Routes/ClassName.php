<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Rest\Request;
use Respect\Rest\Routable;

use function assert;
use function method_exists;

final class ClassName extends AbstractRoute
{
    protected object|null $instance = null;

    /** @param array<int, mixed> $constructorParams */
    public function __construct(
        string $method,
        string $pattern,
        public string $class = '',
        public array $constructorParams = [],
    ) {
        parent::__construct($method, $pattern);
    }

    public function getReflection(string $method): ReflectionFunctionAbstract|null
    {
        /** @var class-string $class */
        $class = $this->class;
        $mirror = new ReflectionClass($class);

        if ($mirror->hasMethod($method)) {
            return new ReflectionMethod($this->class, $method);
        }

        return null;
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, Request $request): mixed
    {
        if ($this->instance === null) {
            $this->instance = $this->createInstance();
        }

        $reflection = $this->getReflection($method);
        if ($reflection !== null) {
            $args = $this->resolveCallbackArguments($reflection, $params, $request);

            return $this->instance->$method(...$args);
        }

        return $this->instance->$method(...$params);
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
