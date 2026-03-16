<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Rest\Request;

final class StaticValue extends AbstractRoute
{
    protected ReflectionMethod $reflection;

    public function __construct(string $method, string $pattern, protected mixed $value)
    {
        parent::__construct($method, $pattern);
        $this->reflection = new ReflectionMethod($this, 'returnValue');
    }

    public function getReflection(string $method): ReflectionFunctionAbstract
    {
        return $this->reflection;
    }

    public function runTarget(string $method, array &$params, Request $request): mixed
    {
        return $this->returnValue();
    }

    public function returnValue(): mixed
    {
        return $this->value;
    }
}
