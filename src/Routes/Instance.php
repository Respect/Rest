<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Rest\Request;
use Respect\Rest\Routable;

final class Instance extends AbstractRoute
{
    public string $class = '';

    protected ReflectionMethod|null $reflection = null;

    public function __construct(string $method, string $pattern, protected object $instance)
    {
        $this->class = $instance::class;

        parent::__construct($method, $pattern);
    }

    public function getReflection(string $method): ReflectionFunctionAbstract
    {
        if ($this->reflection === null) {
            $this->reflection = new ReflectionMethod(
                $this->instance,
                $method,
            );
        }

        return $this->reflection;
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, Request $request): mixed
    {
        if (!$this->instance instanceof Routable) {
            throw new InvalidArgumentException(
                'Route target must be an instance of Respect\Rest\Routable',
            );
        }

        $reflection = $this->getReflection($method);
        $args = $this->resolveCallbackArguments($reflection, $params, $request);

        return $this->instance->$method(...$args);
    }
}
