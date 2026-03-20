<?php

declare(strict_types=1);

namespace Respect\Rest;

use Closure;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Respect\Rest\Routes\AbstractRoute;
use SplObjectStorage;

use function array_filter;
use function array_keys;
use function array_values;
use function count;
use function implode;
use function iterator_to_array;
use function preg_quote;
use function preg_replace;
use function stripos;

final class DispatchEngine implements RequestHandlerInterface
{
    private RoutinePipeline $routinePipeline;

    /** @param (Closure(DispatchContext): void)|null $onContextReady */
    public function __construct(
        private RouteProvider $routeProvider,
        private ResponseFactoryInterface&StreamFactoryInterface $factory,
        private Closure|null $onContextReady = null,
    ) {
        $this->routinePipeline = new RoutinePipeline();
    }

    public function dispatch(ServerRequestInterface $serverRequest): DispatchContext
    {
        $context = new DispatchContext(
            $serverRequest,
            $this->factory,
        );

        return $this->dispatchContext($context);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->dispatch($request)->response();

        return $response ?? $this->factory->createResponse(500);
    }

    public function dispatchContext(DispatchContext $context): DispatchContext
    {
        if ($this->onContextReady !== null) {
            ($this->onContextReady)($context);
        }

        $context->setRoutinePipeline($this->routinePipeline);
        $context->setSideRoutes($this->routeProvider->getSideRoutes());

        if (!$this->isRoutelessDispatch($context) && $context->route === null) {
            $this->routeDispatch($context);
        }

        return $context;
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

    private function isDispatchedToGlobalOptionsMethod(DispatchContext $context): bool
    {
        return $context->method() === 'OPTIONS' && $context->path() === '*';
    }

    private function isRoutelessDispatch(DispatchContext $context): bool
    {
        if (!$this->isDispatchedToGlobalOptionsMethod($context)) {
            return false;
        }

        $allowedMethods = $this->getAllowedMethods($this->routeProvider->getRoutes());

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
        $this->applyBasePath($context);

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

    private function applyBasePath(DispatchContext $context): void
    {
        $basePath = $this->routeProvider->getBasePath();
        if ($basePath === '') {
            return;
        }

        $context->setPath(
            preg_replace(
                '#^' . preg_quote($basePath) . '#',
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

        foreach ($this->routeProvider->getRoutes() as $route) {
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
        foreach ([0, 1, 2] as $rank) {
            $rankMatched = false;

            foreach ($matchedByPath as $route) {
                if ($this->getMethodMatchRank($context, $route) !== $rank) {
                    continue;
                }

                /** @var array<int, mixed> $tempParams */
                $tempParams = $matchedByPath[$route];
                $context->clearResponseMeta();
                $context->route = $route;
                if ($this->routinePipeline->matches($context, $route, $tempParams)) {
                    return $this->configureContext(
                        $context,
                        $route,
                        self::cleanUpParams($tempParams),
                    );
                }

                $rankMatched = true;
            }

            // If a route at this rank matched the method but failed routines,
            // don't fall through to lower-priority ranks
            if ($rankMatched) {
                return false;
            }
        }

        return null;
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
