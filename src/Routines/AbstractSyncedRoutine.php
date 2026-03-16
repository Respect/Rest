<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
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
        if ($reflection instanceof \ReflectionFunctionAbstract) {
            return $reflection->getParameters();
        }

        return [];
    }

    public function execute(Request $request, array $params): mixed
    {
        $callback = $this->getCallback();
        if (is_string($callback)) {
            $reflection = $this->getReflection();
            if ($reflection instanceof ReflectionClass) {
                $routineInstance = $reflection->newInstanceArgs($params);
                return $routineInstance();
            }
        }

        $reflection = $this->getReflection();
        if ($reflection instanceof ReflectionFunction || $reflection instanceof ReflectionMethod) {
            $args = $this->resolveCallbackArguments($reflection, $params, $request);
            return $callback(...$args);
        }

        return $callback(...$params);
    }

    /**
     * Resolves callback arguments, injecting PSR-7 objects for type-hinted parameters.
     *
     * @param array<int, mixed> $params
     * @return array<int, mixed>
     */
    protected function resolveCallbackArguments(
        \ReflectionFunctionAbstract $reflection,
        array $params,
        Request $request,
    ): array {
        $refParams = $reflection->getParameters();

        if ($refParams === []) {
            return $params;
        }

        $args = [];
        $paramIndex = 0;
        $hasPsrInjection = false;

        foreach ($refParams as $refParam) {
            $type = $refParam->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if (is_a($typeName, ServerRequestInterface::class, true)) {
                    $args[] = $request->serverRequest;
                    $hasPsrInjection = true;
                    continue;
                }

                if (is_a($typeName, ResponseInterface::class, true) && $request->route?->responseFactory !== null) {
                    $args[] = $request->route->responseFactory->createResponse();
                    $hasPsrInjection = true;
                    continue;
                }
            }

            $args[] = $params[$paramIndex] ?? ($refParam->isDefaultValueAvailable() ? $refParam->getDefaultValue() : null);
            $paramIndex++;
        }

        if (!$hasPsrInjection) {
            return $params;
        }

        return $args;
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
