<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Rest\Request;

use function method_exists;

abstract class ControllerRoute extends AbstractRoute
{
    /** @var array<string, ReflectionMethod> */
    private array $reflections = [];

    protected object|string $reflectionTarget;

    public function getReflection(string $method): ReflectionFunctionAbstract|null
    {
        if (!method_exists($this->reflectionTarget, $method)) {
            return null;
        }

        return $this->reflections[$method] ??= new ReflectionMethod($this->reflectionTarget, $method);
    }

    public function getTargetMethod(string $method): string
    {
        if ($method !== 'HEAD') {
            return $method;
        }

        if ($this->getReflection('HEAD') !== null) {
            return 'HEAD';
        }

        if ($this->getReflection('GET') !== null) {
            return 'GET';
        }

        return $method;
    }

    /** @param array<int, mixed> $params */
    protected function invokeTarget(object $target, string $method, array &$params, Request $request): mixed
    {
        $reflection = $this->getReflection($method);
        if ($reflection !== null) {
            $args = $this->resolveCallbackArguments($reflection, $params, $request);

            return $target->$method(...$args);
        }

        return $target->$method(...$params);
    }
}
