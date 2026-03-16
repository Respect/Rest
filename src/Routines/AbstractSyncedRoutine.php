<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use ReflectionClass;
use Closure;
use Respect\Rest\Request;

/** Base class for routines that sync parameters */
abstract class AbstractSyncedRoutine extends AbstractRoutine implements ParamSynced
{
    protected ?\Reflector $reflection = null;

    public function getParameters(): array
    {
        $reflection = $this->getReflection();
        if (!$reflection instanceof ReflectionObject && !$reflection instanceof ReflectionClass) {
            return $this->getReflection()->getParameters();
        }

        return [];
    }

    public function execute(Request $request, array $params): mixed
    {
        $callback = $this->getCallback();
        if (is_string($callback)) {
            $reflection      = $this->getReflection();
            $routineInstance = $reflection->newInstanceArgs($params);

            return $routineInstance();
        }

        return $callback(...$params);
    }

    protected function getReflection(): \Reflector
    {
        $callback = $this->getCallback();
        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        } elseif ($callback instanceof Closure) {
            return new ReflectionFunction($callback);
        } elseif (is_string($callback)) {
            return new ReflectionClass($callback);
        } else {
            return new ReflectionObject($callback);
        }
    }
}
