<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use ReflectionFunctionAbstract;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Parameter\Resolver;
use Respect\Rest\DispatchContext;

use function array_merge;

class Callback extends AbstractRoute
{
    protected ReflectionFunctionAbstract|null $reflection = null;

    /** @param array<int, mixed> $arguments */
    public function __construct(
        NamespaceLookup $routineLookup,
        string $method,
        string $pattern,
        /** @var callable */
        protected $callback,
        /** @var array<int, mixed> */
        public private(set) array $arguments = [],
    ) {
        parent::__construct($routineLookup, $method, $pattern);
    }

    public function getCallbackReflection(): ReflectionFunctionAbstract
    {
        return Resolver::reflectCallable($this->callback);
    }

    public function getReflection(string $method): ReflectionFunctionAbstract
    {
        if ($this->reflection === null) {
            $this->reflection = $this->getCallbackReflection();
        }

        return $this->reflection;
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, DispatchContext $context): mixed
    {
        $reflection = $this->getReflection($method);
        $args = $context->resolver()->resolve($reflection, array_merge($params, $this->arguments));

        return ($this->callback)(...$args);
    }
}
