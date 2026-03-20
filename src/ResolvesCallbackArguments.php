<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunctionAbstract;
use ReflectionNamedType;

use function is_a;

/** Shared PSR-7 argument injection for routes and routines */
trait ResolvesCallbackArguments
{
    /**
     * Resolves callback arguments by inspecting parameter types via reflection.
     *
     * PSR-7 typed parameters (ServerRequestInterface, ResponseInterface) are
     * injected automatically. All other parameters consume URL params positionally.
     *
     * @param array<int, mixed> $params URL-extracted parameters
     *
     * @return array<int, mixed> Resolved argument list
     */
    protected function resolveCallbackArguments(
        ReflectionFunctionAbstract $reflection,
        array $params,
        DispatchContext $context,
    ): array {
        $refParams = $reflection->getParameters();

        // No declared parameters — pass all URL params through (supports func_get_args())
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
                    $args[] = $context->request;
                    $hasPsrInjection = true;
                    continue;
                }

                if (is_a($typeName, ResponseInterface::class, true)) {
                    $args[] = $context->factory->createResponse();
                    $hasPsrInjection = true;
                    continue;
                }
            }

            $default = $refParam->isDefaultValueAvailable() ? $refParam->getDefaultValue() : null;
            $args[] = $params[$paramIndex] ?? $default;
            $paramIndex++;
        }

        // No PSR-7 injection happened — pass params directly (faster, preserves original behavior)
        if (!$hasPsrInjection) {
            return $params;
        }

        return $args;
    }
}
