<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Rest\Request;
use Respect\Rest\Routable;

class Factory extends AbstractRoute
{
    public string $class = '';
    protected ?object $instance = null;
    /** @var callable */
    public $factory;
    protected ?ReflectionMethod $reflection = null;

    public function __construct(string $method, string $pattern, string $class, callable $factory)
    {
        $this->factory = $factory;
        $this->class = $class;
        parent::__construct($method, $pattern);
    }

    public function getReflection(string $method): ?ReflectionFunctionAbstract
    {
        if ($this->reflection === null) {
            $this->reflection = new ReflectionMethod(
                $this->class,
                $method
            );
        }

        return $this->reflection;
    }

    public function runTarget(string $method, array &$params, Request $request): mixed
    {
        if ($this->instance === null) {
            $this->instance = ($this->factory)($method, $params);
        }

        if (!$this->instance instanceof Routable) {
            throw new InvalidArgumentException(
                'Routed classes must implement the Respect\\Rest\\Routable interface'
            );
        }

        $reflection = $this->getReflection($method);
        if ($reflection !== null) {
            $args = $this->resolveCallbackArguments($reflection, $params, $request);
            return $this->instance->$method(...$args);
        }

        return $this->instance->$method(...$params);
    }
}
