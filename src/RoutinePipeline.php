<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Http\Message\ResponseInterface;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\ProxyableBy;
use Respect\Rest\Routines\ProxyableThrough;
use Respect\Rest\Routines\ProxyableWhen;

use function is_callable;

final class RoutinePipeline
{
    /** @param array<int, mixed> $params */
    public function matches(DispatchContext $context, AbstractRoute $route, array $params = []): bool
    {
        foreach ($route->routines as $routine) {
            if (
                $routine instanceof ProxyableWhen
                && !$routine->when($context, $params)
            ) {
                return false;
            }
        }

        return true;
    }

    public function processBy(DispatchContext $context, AbstractRoute $route): mixed
    {
        foreach ($route->routines as $routine) {
            if (!$routine instanceof ProxyableBy) {
                continue;
            }

            $result = $routine->by($context, $context->params);

            if ($result instanceof AbstractRoute || $result instanceof ResponseInterface || $result === false) {
                return $result;
            }
        }

        return null;
    }

    public function processThrough(DispatchContext $context, AbstractRoute $route, mixed $response): mixed
    {
        foreach ($route->routines as $routine) {
            if (!($routine instanceof ProxyableThrough)) {
                continue;
            }

            $proxyCallback = $routine->through($context, $context->params);

            if (!is_callable($proxyCallback)) {
                continue;
            }

            $response = $proxyCallback($response);
        }

        return $response;
    }
}
