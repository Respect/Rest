<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Rest\Routes\AbstractRoute;
use SplObjectStorage;

use function array_filter;
use function array_keys;
use function array_values;
use function count;
use function implode;
use function is_array;
use function iterator_to_array;
use function preg_quote;
use function preg_replace;
use function stripos;

final class DispatchEngine
{
    private RoutinePipeline $routinePipeline;

    public function __construct(private Router $router)
    {
        $this->routinePipeline = new RoutinePipeline();
    }

    public function dispatch(ServerRequestInterface $serverRequest): DispatchContext
    {
        $context = new DispatchContext($serverRequest);
        $context->responseFactory = $this->router->responseFactory;

        return $this->dispatchContext($context);
    }

    public function dispatchContext(DispatchContext $context): DispatchContext
    {
        $this->router->isAutoDispatched = false;
        $this->router->context = $context;
        $context->responseFactory ??= $this->router->responseFactory;
        $context->setRoutinePipeline($this->routinePipeline);

        if (!$this->isRoutelessDispatch($context) && $context->route === null) {
            $this->routeDispatch($context);
        }

        return $context;
    }

    public function run(DispatchContext $context): ResponseInterface|null
    {
        return $this->dispatchContext($context)->response();
    }

    /**
     * @param array<int, AbstractRoute> $routes
     *
     * @return array<int, string>
     */
    private function getAllowedMethods(array $routes): array
    {
        $allowedMethods = [];

        foreach ($routes as $route) {
            foreach ($route->getAllowedMethods() as $method) {
                $allowedMethods[$method] = true;
            }
        }

        if ($allowedMethods === []) {
            return [];
        }

        $allowedMethods['OPTIONS'] = true;

        return array_keys($allowedMethods);
    }

    private function hasDispatchedOverriddenMethod(DispatchContext $context): bool
    {
        if (!$this->router->methodOverriding || $context->method() !== 'POST') {
            return false;
        }

        $parsedBody = $context->request->getParsedBody();
        $queryParams = $context->request->getQueryParams();

        return (is_array($parsedBody) && isset($parsedBody['_method'])) || isset($queryParams['_method']);
    }

    private function isDispatchedToGlobalOptionsMethod(DispatchContext $context): bool
    {
        return $context->method() === 'OPTIONS' && $context->path() === '*';
    }

    private function isRoutelessDispatch(DispatchContext $context): bool
    {
        if ($this->hasDispatchedOverriddenMethod($context)) {
            $parsedBody = $context->request->getParsedBody();
            $queryParams = $context->request->getQueryParams();
            $bodyMethod = is_array($parsedBody) ? ($parsedBody['_method'] ?? null) : null;
            $overrideMethod = $bodyMethod ?? $queryParams['_method'] ?? null;
            if ($overrideMethod !== null) {
                $context->overrideMethod((string) $overrideMethod);
            }
        }

        if (!$this->isDispatchedToGlobalOptionsMethod($context)) {
            return false;
        }

        $allowedMethods = $this->getAllowedMethods($this->router->getRoutes());

        if ($allowedMethods) {
            $context->prepareResponse(
                204,
                ['Allow' => $this->getAllowHeaderValue($allowedMethods)],
            );
        } else {
            $context->prepareResponse(404);
        }

        return true;
    }

    private function routeDispatch(DispatchContext $context): void
    {
        $this->applyVirtualHost($context);

        $matchedByPath = $this->getMatchedRoutesByPath($context);
        /** @var array<int, AbstractRoute> $matchedArray */
        $matchedArray = iterator_to_array($matchedByPath);
        $allowedMethods = $this->getAllowedMethods($matchedArray);

        if ($context->method() === 'OPTIONS' && $allowedMethods) {
            $this->handleOptionsRequest($context, $allowedMethods, $matchedByPath);
        } elseif (count($matchedByPath) === 0) {
            $context->prepareResponse(404);
        } else {
            $this->resolveRouteMatch(
                $context,
                $this->routineMatch($context, $matchedByPath),
                $allowedMethods,
            );
        }
    }

    private function applyVirtualHost(DispatchContext $context): void
    {
        $virtualHost = $this->router->getVirtualHost();
        if ($virtualHost === null) {
            return;
        }

        $context->setPath(
            preg_replace(
                '#^' . preg_quote($virtualHost) . '#',
                '',
                $context->path(),
            ) ?? $context->path(),
        );
    }

    /** @param array<int, mixed> $params */
    private function configureContext(
        DispatchContext $context,
        AbstractRoute $route,
        array $params = [],
    ): DispatchContext {
        $context->route = $route;
        $context->params = $params;

        return $context;
    }

    /** @return SplObjectStorage<AbstractRoute, array<int, mixed>> */
    private function getMatchedRoutesByPath(DispatchContext $context): SplObjectStorage
    {
        /** @var SplObjectStorage<AbstractRoute, array<int, mixed>> $matched */
        $matched = new SplObjectStorage();

        foreach ($this->router->getRoutes() as $route) {
            $params = [];
            if (!$this->matchRoute($context, $route, $params)) {
                continue;
            }

            $matched[$route] = $params;
        }

        return $matched;
    }

    /** @param array<string> $allowedMethods */
    private function getAllowHeaderValue(array $allowedMethods): string
    {
        return implode(', ', $allowedMethods);
    }

    /**
     * @param array<string> $allowedMethods
     * @param SplObjectStorage<AbstractRoute, array<int, mixed>> $matchedByPath
     */
    private function handleOptionsRequest(
        DispatchContext $context,
        array $allowedMethods,
        SplObjectStorage $matchedByPath,
    ): void {
        if ($this->hasExplicitOptionsRoute($matchedByPath)) {
            $matchedContext = $this->routineMatch($context, $matchedByPath);
            if ($matchedContext instanceof DispatchContext) {
                $matchedContext->setResponseHeader('Allow', $this->getAllowHeaderValue($allowedMethods));
            }

            $this->resolveRouteMatch($context, $matchedContext, $allowedMethods);

            return;
        }

        $context->prepareResponse(
            204,
            ['Allow' => $this->getAllowHeaderValue($allowedMethods)],
        );
    }

    /** @param array<string> $allowedMethods */
    private function resolveRouteMatch(
        DispatchContext $context,
        DispatchContext|bool|null $matchedContext,
        array $allowedMethods = [],
    ): void {
        if ($matchedContext instanceof DispatchContext || $context->hasPreparedResponse()) {
            return;
        }

        if ($matchedContext === false) {
            $context->prepareResponse(400);

            return;
        }

        if ($allowedMethods === []) {
            return;
        }

        $context->prepareResponse(
            405,
            ['Allow' => $this->getAllowHeaderValue($allowedMethods)],
        );
    }

    private function getMethodMatchRank(DispatchContext $context, AbstractRoute $route): int|null
    {
        if (stripos($context->method(), '__') === 0) {
            return null;
        }

        return $route->getMethodMatchRank($context->method());
    }

    /** @param SplObjectStorage<AbstractRoute, array<int, mixed>> $matchedByPath */
    private function hasExplicitOptionsRoute(SplObjectStorage $matchedByPath): bool
    {
        foreach ($matchedByPath as $route) {
            if ($route->method === 'OPTIONS') {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $params */
    private function matchRoute(
        DispatchContext $context,
        AbstractRoute $route,
        array &$params = [],
    ): bool {
        if (!$route->match($context, $params)) {
            return false;
        }

        $context->route = $route;

        return true;
    }

    /** @param SplObjectStorage<AbstractRoute, array<int, mixed>> $matchedByPath */
    private function routineMatch(
        DispatchContext $context,
        SplObjectStorage $matchedByPath,
    ): DispatchContext|bool|null {
        $badRequest = false;

        foreach ([0, 1, 2] as $rank) {
            foreach ($matchedByPath as $route) {
                if ($this->getMethodMatchRank($context, $route) !== $rank) {
                    continue;
                }

                /** @var array<int, mixed> $tempParams */
                $tempParams = $matchedByPath[$route];
                $context->clearResponseMeta();
                $context->route = $route;
                if ($this->routinePipeline->matches($context, $tempParams)) {
                    return $this->configureContext(
                        $context,
                        $route,
                        self::cleanUpParams($tempParams),
                    );
                }

                $badRequest = true;
            }
        }

        return $badRequest ? false : null;
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return array<int, mixed>
     */
    private static function cleanUpParams(array $params): array
    {
        return array_values(
            array_filter(
                $params,
                static fn(mixed $param): bool => $param !== '',
            ),
        );
    }
}
