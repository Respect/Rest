<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;

class Callback extends AbstractRoute
{
    /** @var callable */
    protected $callback;

    /** @var array<int, mixed> */
    public array $arguments;

    protected ?ReflectionFunctionAbstract $reflection = null;

    /** @param array<int, mixed> $arguments */
    public function __construct(
        string $method,
        string $pattern,
        callable $callback,
        array $arguments = []
    ) {
        $this->callback = $callback;
        $this->arguments = $arguments;
        parent::__construct($method, $pattern);
    }

    public function getCallbackReflection(): ReflectionFunctionAbstract
    {
        if (is_array($this->callback)) {
            return new ReflectionMethod($this->callback[0], $this->callback[1]);
        }

        return new ReflectionFunction($this->callback);
    }

    public function getReflection(string $method): ReflectionFunctionAbstract
    {
        if ($this->reflection === null) {
            $this->reflection = $this->getCallbackReflection();
        }

        return $this->reflection;
    }

    public function runTarget(string $method, array &$params): mixed
    {
        return ($this->callback)(...array_merge($params, $this->arguments));
    }
}
