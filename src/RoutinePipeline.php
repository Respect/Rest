<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Http\Message\ResponseInterface;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\ProxyableBy;
use Respect\Rest\Routines\ProxyableThrough;
use Respect\Rest\Routines\ProxyableWhen;

use function assert;
use function is_callable;

final class RoutinePipeline
{
    /** @param array<int, mixed> $params */
    public function matches(DispatchContext $context, array $params = []): bool
    {
        assert($context->route !== null);

        foreach ($context->route->routines as $routine) {
            if (
                $routine instanceof ProxyableWhen
                && !$context->routineCall('when', $context->method(), $routine, $params)
            ) {
                return false;
            }
        }

        return true;
    }

    public function processBy(DispatchContext $context): mixed
    {
        assert($context->route !== null);

        foreach ($context->route->routines as $routine) {
            if (!$routine instanceof ProxyableBy) {
                continue;
            }

            $result = $context->routineCall(
                'by',
                $context->method(),
                $routine,
                $context->params,
            );

            if ($result instanceof AbstractRoute || $result instanceof ResponseInterface || $result === false) {
                return $result;
            }
        }

        return null;
    }

    public function processThrough(DispatchContext $context, mixed $response): mixed
    {
        assert($context->route !== null);

        foreach ($context->route->routines as $routine) {
            if (!($routine instanceof ProxyableThrough)) {
                continue;
            }

            $proxyCallback = $context->routineCall(
                'through',
                $context->method(),
                $routine,
                $context->params,
            );

            if (!is_callable($proxyCallback)) {
                continue;
            }

            $response = $proxyCallback($response);
        }

        return $response;
    }
}
