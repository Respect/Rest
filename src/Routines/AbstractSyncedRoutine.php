<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionObject;
use ReflectionParameter;
use Reflector;
use Respect\Rest\DispatchContext;
use Respect\Rest\ResolvesCallbackArguments;

use function assert;
use function is_array;
use function is_callable;
use function is_string;

/** Base class for routines that sync parameters */
// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousAbstractClassNaming.SuperfluousPrefix
abstract class AbstractSyncedRoutine extends AbstractRoutine implements ParamSynced
{
    use ResolvesCallbackArguments;

    protected Reflector|null $reflection = null;

    /** @return array<int, ReflectionParameter> */
    public function getParameters(): array
    {
        $reflection = $this->getReflection();
        if ($reflection instanceof ReflectionFunctionAbstract) {
            return $reflection->getParameters();
        }

        return [];
    }

    /** @param array<int, mixed> $params */
    public function execute(DispatchContext $context, array $params): mixed
    {
        $callback = $this->getCallback();
        if (is_string($callback)) {
            $reflection = $this->getReflection();
            if ($reflection instanceof ReflectionClass) {
                $routineInstance = $reflection->newInstanceArgs($params);
                assert(is_callable($routineInstance));

                return $routineInstance();
            }
        }

        $reflection = $this->getReflection();
        if ($reflection instanceof ReflectionFunction || $reflection instanceof ReflectionMethod) {
            $args = $this->resolveCallbackArguments($reflection, $params, $context);

            return $callback(...$args);
        }

        return $callback(...$params);
    }

    protected function getReflection(): Reflector
    {
        $callback = $this->getCallback();
        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        }

        if ($callback instanceof Closure) {
            return new ReflectionFunction($callback);
        }

        if (is_string($callback)) {
            /** @var class-string $callback */ // phpcs:ignore SlevomatCodingStandard.Commenting.InlineDocCommentDeclaration.MissingVariable

            return new ReflectionClass($callback);
        }

        return new ReflectionObject($callback);
    }
}
