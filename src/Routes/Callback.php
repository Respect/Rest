<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use Respect\Rest\DispatchContext;

use function array_merge;
use function is_array;

class Callback extends AbstractRoute
{
    protected ReflectionFunctionAbstract|null $reflection = null;

    /** @param array<int, mixed> $arguments */
    public function __construct(
        string $method,
        string $pattern,
        /** @var callable */
        protected $callback,
        /** @var array<int, mixed> */
        public array $arguments = [],
    ) {
        parent::__construct($method, $pattern);
    }

    public function getCallbackReflection(): ReflectionFunctionAbstract
    {
        if (is_array($this->callback)) {
            return new ReflectionMethod($this->callback[0], $this->callback[1]);
        }

        return new ReflectionFunction(Closure::fromCallable($this->callback));
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
        $args = $this->resolveCallbackArguments($reflection, array_merge($params, $this->arguments), $context);

        return ($this->callback)(...$args);
    }
}
