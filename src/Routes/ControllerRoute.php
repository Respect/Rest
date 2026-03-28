<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Rest\DispatchContext;

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

    /** @return array<int, string> */
    public function getAllowedMethods(): array
    {
        if ($this->method !== 'ANY') {
            return parent::getAllowedMethods();
        }

        $allowedMethods = [];

        foreach (self::CORE_METHODS as $method) {
            if ($method === 'HEAD') {
                if ($this->getReflection('HEAD') !== null || $this->getReflection('GET') !== null) {
                    $allowedMethods[] = 'HEAD';
                }

                continue;
            }

            if ($this->getReflection($method) === null) {
                continue;
            }

            $allowedMethods[] = $method;
        }

        return $allowedMethods;
    }

    public function getMethodMatchRank(string $method): int|null
    {
        if ($this->method !== 'ANY') {
            return parent::getMethodMatchRank($method);
        }

        if ($method === 'HEAD' && $this->getReflection('HEAD') !== null) {
            return 1;
        }

        if ($method !== 'HEAD' && $this->getReflection($method) !== null) {
            return 1;
        }

        if ($method === 'HEAD' && $this->getReflection('GET') !== null) {
            return 2;
        }

        return null;
    }

    public function getTargetMethod(string $method): string
    {
        if ($method !== 'HEAD') {
            return $method;
        }

        if ($this->getReflection('HEAD') !== null) {
            return 'HEAD';
        }

        return 'GET';
    }

    /** @param array<int, mixed> $params */
    protected function invokeTarget(object $target, string $method, array &$params, DispatchContext $context): mixed
    {
        $reflection = $this->getReflection($method);
        if ($reflection !== null) {
            $args = $context->resolver()->resolve($reflection, $params);

            return $target->$method(...$args);
        }

        return $target->$method(...$params);
    }
}
