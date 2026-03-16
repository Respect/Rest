<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Rest\Request;
use Respect\Rest\Routable;

final class Factory extends AbstractRoute
{
    protected ?object $instance = null;
    protected ?ReflectionMethod $reflection = null;

    public function __construct(
        string $method,
        string $pattern,
        public string $class = '',
        /** @var callable */
        public $factory = null,
    ) {
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
