<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Rest\DispatchContext;

final class StaticValue extends AbstractRoute
{
    protected ReflectionMethod $reflection;

    public function __construct(NamespaceLookup $routineLookup, string $method, string $pattern, protected mixed $value)
    {
        parent::__construct($routineLookup, $method, $pattern);

        $this->reflection = new ReflectionMethod($this, 'returnValue');
    }

    public function getReflection(string $method): ReflectionFunctionAbstract
    {
        return $this->reflection;
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, DispatchContext $context): mixed
    {
        return $this->returnValue();
    }

    public function returnValue(): mixed
    {
        return $this->value;
    }
}
