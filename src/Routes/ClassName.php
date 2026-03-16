<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Rest\Request;
use Respect\Rest\Routable;

class ClassName extends AbstractRoute
{
    public string $class = '';

    /** @var array<int, mixed> */
    public array $constructorParams = [];

    protected ?object $instance = null;

    /** @param array<int, mixed> $params */
    public function __construct(
        string $method,
        string $pattern,
        string $class,
        array $params = []
    ) {
        $this->class = $class;
        $this->constructorParams = $params;
        parent::__construct($method, $pattern);
    }

    protected function createInstance(): Routable
    {
        $className = $this->class;
        $reflection = new ReflectionClass($className);

        if (!$reflection->implementsInterface(Routable::class)) {
            throw new InvalidArgumentException(
                'Routed classes must implement Respect\\Rest\\Routable'
            );
        }

        if (
            empty($this->constructorParams)
            || !method_exists($this->class, '__construct')
        ) {
            return new $className();
        }

        return $reflection->newInstanceArgs($this->constructorParams);
    }

    public function getReflection(string $method): ?ReflectionFunctionAbstract
    {
        $mirror = new ReflectionClass($this->class);

        if ($mirror->hasMethod($method)) {
            return new ReflectionMethod($this->class, $method);
        }

        return null;
    }

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
}
