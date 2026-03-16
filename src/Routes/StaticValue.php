<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use ReflectionFunctionAbstract;
use ReflectionMethod;

class StaticValue extends AbstractRoute
{
    protected mixed $value;
    protected ReflectionMethod $reflection;

    public function __construct(string $method, string $pattern, mixed $value)
    {
        $this->value = $value;
        parent::__construct($method, $pattern);
        $this->reflection = new ReflectionMethod($this, 'returnValue');
    }

    public function getReflection(string $method): ReflectionFunctionAbstract
    {
        return $this->reflection;
    }

    public function runTarget(string $method, array &$params): mixed
    {
        return $this->returnValue();
    }

    public function returnValue(): mixed
    {
        return $this->value;
    }
}
