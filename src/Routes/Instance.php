<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Rest\Routable;

class Instance extends AbstractRoute
{
    public string $class = '';
    protected object $instance;
    protected ?ReflectionMethod $reflection = null;

    public function __construct(string $method, string $pattern, object $instance)
    {
        $this->instance = $instance;
        $this->class = get_class($instance);
        parent::__construct($method, $pattern);
    }

    public function getReflection(string $method): ?ReflectionFunctionAbstract
    {
        if ($this->reflection === null) {
            $this->reflection = new ReflectionMethod(
                $this->instance,
                $method
            );
        }

        return $this->reflection;
    }

    public function runTarget(string $method, array &$params): mixed
    {
        if (!$this->instance instanceof Routable) {
            throw new InvalidArgumentException(
                'Route target must be an instance of Respect\Rest\Routable'
            );
        }

        return $this->instance->$method(...$params);
    }
}
