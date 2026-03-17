<?php

declare(strict_types=1);

namespace Respect\Rest;

use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use Respect\Rest\Routes\AbstractRoute;
use SplObjectStorage;
use Throwable;

use function array_filter;
use function array_keys;
use function array_pop;
use function array_values;
use function assert;
use function class_exists;
use function count;
use function implode;
use function interface_exists;
use function is_array;
use function is_callable;
use function is_string;
use function iterator_to_array;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function stripos;
use function substr_count;
use function trigger_error;
use function usort;

use const E_USER_ERROR;

/**
 * A router that contains many instances of routes.
 *
 * @method AbstractRoute get(string $path, mixed $routeTarget)
 * @method AbstractRoute post(string $path, mixed $routeTarget)
 * @method AbstractRoute put(string $path, mixed $routeTarget)
 * @method AbstractRoute delete(string $path, mixed $routeTarget)
 * @method AbstractRoute head(string $path, mixed $routeTarget)
 * @method AbstractRoute options(string $path, mixed $routeTarget)
 * @method AbstractRoute patch(string $path, mixed $routeTarget)
 * @method AbstractRoute any(string $path, mixed $routeTarget)
 */
final class Router
{
    public bool $isAutoDispatched = true;

    public bool $methodOverriding = false;

    public DispatchContext|null $context = null;

    /** @var array<int, Routines\Routinable> */
    protected array $globalRoutines = [];

    /** @var array<int, AbstractRoute> */
    protected array $routes = [];

    /** @var array<int, AbstractRoute> */
    protected array $sideRoutes = [];

    /** Used by tests for named route attributes */
    public mixed $allMembers = null;

    public function __construct(
        public ResponseFactoryInterface $responseFactory,
        protected string|null $virtualHost = null,
    ) {
    }

    public function always(string $routineName, mixed ...$params): static
    {
        /** @var class-string<Routines\Routinable> $routineClassName */
        $routineClassName = 'Respect\\Rest\\Routines\\' . $routineName;
        $routineClass = new ReflectionClass($routineClassName);
        $routineInstance = $routineClass->newInstanceArgs($params);
        $this->globalRoutines[] = $routineInstance;

        foreach ($this->routes as $route) {
            $route->appendRoutine($routineInstance);
        }

        return $this;
    }

    public function appendRoute(AbstractRoute $route): static
    {
        $this->routes[] = $route;
        $route->sideRoutes = &$this->sideRoutes;
        $route->virtualHost = $this->virtualHost;
        $route->responseFactory = $this->responseFactory;

        foreach ($this->globalRoutines as $routine) {
            $route->appendRoutine($routine);
        }

        $this->sortRoutesByComplexity();

        return $this;
    }

    public function appendSideRoute(AbstractRoute $route): static
    {
        $this->sideRoutes[] = $route;
        $route->responseFactory = $this->responseFactory;

        foreach ($this->globalRoutines as $routine) {
            $route->appendRoutine($routine);
        }

        return $this;
    }

    /** @param array<int, mixed> $arguments */
    public function callbackRoute(
        string $method,
        string $path,
        callable $callback,
        array $arguments = [],
    ): Routes\Callback {
        $route = new Routes\Callback($method, $path, $callback, $arguments);
        $this->appendRoute($route);

        return $route;
    }

    /** @param array<int, mixed> $arguments */
    public function classRoute(string $method, string $path, string $class, array $arguments = []): Routes\ClassName
    {
        $route = new Routes\ClassName($method, $path, $class, $arguments);
        $this->appendRoute($route);

        return $route;
    }

    public function dispatch(ServerRequestInterface $serverRequest): DispatchContext
    {
        $context = new DispatchContext($serverRequest);
        $context->responseFactory = $this->responseFactory;

        return $this->dispatchContext($context);
    }

    public function dispatchContext(DispatchContext $context): DispatchContext
    {
        $context->responseFactory ??= $this->responseFactory;

        if (!$this->isRoutelessDispatch($context) && $context->route === null) {
            $this->routeDispatch();
        }

        return $context;
    }

    public function exceptionRoute(string $className, callable $callback): Routes\Exception
    {
        $route = new Routes\Exception($className, $callback);
        $this->appendSideRoute($route);

        return $route;
    }

    public function errorRoute(callable $callback): Routes\Error
    {
        $route = new Routes\Error($callback);
        $this->appendSideRoute($route);

        return $route;
    }

    public function factoryRoute(string $method, string $path, string $className, callable $factory): Routes\Factory
    {
        $route = new Routes\Factory($method, $path, $className, $factory);
        $this->appendRoute($route);

        return $route;
    }

    /**
     * @param array<int, AbstractRoute> $routes
     *
     * @return array<int, string>
     */
    public function getAllowedMethods(array $routes): array
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

    public function hasDispatchedOverridenMethod(): bool
    {
        if (!$this->context || !$this->methodOverriding || $this->context->method() !== 'POST') {
            return false;
        }

        // Read _method from the PSR-7 parsed body or query params
        $parsedBody = $this->context->request->getParsedBody();
        $queryParams = $this->context->request->getQueryParams();

        return (is_array($parsedBody) && isset($parsedBody['_method'])) || isset($queryParams['_method']);
    }

    public function instanceRoute(string $method, string $path, object $instance): Routes\Instance
    {
        $route = new Routes\Instance($method, $path, $instance);
        $this->appendRoute($route);

        return $route;
    }

    public function isDispatchedToGlobalOptionsMethod(): bool
    {
        return $this->context !== null
            && $this->context->method() === 'OPTIONS'
            && $this->context->path() === '*';
    }

    public function isRoutelessDispatch(DispatchContext $context): bool
    {
        $this->isAutoDispatched = false;
        $this->context = $context;

        if ($this->hasDispatchedOverridenMethod()) {
            $parsedBody = $context->request->getParsedBody();
            $queryParams = $context->request->getQueryParams();
            $bodyMethod = is_array($parsedBody) ? ($parsedBody['_method'] ?? null) : null;
            $overrideMethod = $bodyMethod ?? $queryParams['_method'] ?? null;
            if ($overrideMethod !== null) {
                $context->overrideMethod((string) $overrideMethod);
            }
        }

        if ($this->isDispatchedToGlobalOptionsMethod()) {
            $allowedMethods = $this->getAllowedMethods($this->routes);

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

        return false;
    }

    public function routeDispatch(): void
    {
        assert($this->context !== null);
        $context = $this->context;
        $this->applyVirtualHost();

        $matchedByPath = $this->getMatchedRoutesByPath();
        /** @var array<int, AbstractRoute> $matchedArray */
        $matchedArray = iterator_to_array($matchedByPath);
        $allowedMethods = $this->getAllowedMethods($matchedArray);

        if ($context->method() === 'OPTIONS' && $allowedMethods) {
            $this->handleOptionsRequest($allowedMethods, $matchedByPath);
        } elseif (count($matchedByPath) === 0) {
            $context->prepareResponse(404);
        } else {
            $this->resolveRouteMatch(
                $this->routineMatch($matchedByPath),
                $allowedMethods,
            );
        }
    }

    public function run(DispatchContext $context): ResponseInterface|null
    {
        $route = $this->dispatchContext($context);

        return $route->response();
    }

    public function staticRoute(string $method, string $path, mixed $staticValue): Routes\StaticValue
    {
        $route = new Routes\StaticValue($method, $path, $staticValue);
        $this->appendRoute($route);

        return $route;
    }

    public static function compareOcurrences(string $patternA, string $patternB, string $sub): bool
    {
        return substr_count($patternA, $sub) < substr_count($patternB, $sub);
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return array<int, mixed>
     */
    protected static function cleanUpParams(array $params): array
    {
        return array_values(
            array_filter(
                $params,
                static fn(mixed $param): bool => $param !== '',
            ),
        );
    }

    protected function applyVirtualHost(): void
    {
        assert($this->context !== null);
        if (!$this->virtualHost) {
            return;
        }

        $this->context->setPath(
            preg_replace(
                '#^' . preg_quote($this->virtualHost) . '#',
                '',
                $this->context->path(),
            ) ?? $this->context->path(),
        );
    }

    /** @param array<int, mixed> $params */
    protected function configureContext(
        DispatchContext $context,
        AbstractRoute $route,
        array $params = [],
    ): DispatchContext {
        $context->route = $route;
        $context->params = $params;

        return $context;
    }

    /** @return SplObjectStorage<AbstractRoute, array<int, mixed>> */
    protected function getMatchedRoutesByPath(): SplObjectStorage
    {
        assert($this->context !== null);
        /** @var SplObjectStorage<AbstractRoute, array<int, mixed>> $matched */
        $matched = new SplObjectStorage();

        foreach ($this->routes as $route) {
            $params = [];
            if (!$this->matchRoute($this->context, $route, $params)) {
                continue;
            }

            $matched[$route] = $params;
        }

        return $matched;
    }

    /** @param array<string> $allowedMethods */
    protected function getAllowHeaderValue(array $allowedMethods): string
    {
        return implode(', ', $allowedMethods);
    }

    /**
     * @param array<string> $allowedMethods
     * @param SplObjectStorage<AbstractRoute, array<int, mixed>> $matchedByPath
     */
    protected function handleOptionsRequest(array $allowedMethods, SplObjectStorage $matchedByPath): void
    {
        if ($this->hasExplicitOptionsRoute($matchedByPath)) {
            $matchedContext = $this->routineMatch($matchedByPath);
            if ($matchedContext instanceof DispatchContext) {
                $matchedContext->setResponseHeader('Allow', $this->getAllowHeaderValue($allowedMethods));
            }

            $this->resolveRouteMatch($matchedContext, $allowedMethods);

            return;
        }

        assert($this->context !== null);
        $this->context->prepareResponse(
            204,
            ['Allow' => $this->getAllowHeaderValue($allowedMethods)],
        );
    }

    /** @param array<string> $allowedMethods */
    protected function resolveRouteMatch(DispatchContext|bool|null $matchedContext, array $allowedMethods = []): void
    {
        assert($this->context !== null);
        if ($matchedContext instanceof DispatchContext || $this->context->hasPreparedResponse()) {
            return;
        }

        if ($matchedContext === false) {
            $this->context->prepareResponse(400);

            return;
        }

        if ($allowedMethods === []) {
            return;
        }

        $this->context->prepareResponse(
            405,
            ['Allow' => $this->getAllowHeaderValue($allowedMethods)],
        );
    }

    protected function getMethodMatchRank(AbstractRoute $route): int|null
    {
        assert($this->context !== null);

        if (stripos($this->context->method(), '__') === 0) {
            return null;
        }

        return $route->getMethodMatchRank($this->context->method());
    }

    /** @param SplObjectStorage<AbstractRoute, array<int, mixed>> $matchedByPath */
    protected function hasExplicitOptionsRoute(SplObjectStorage $matchedByPath): bool
    {
        foreach ($matchedByPath as $route) {
            if ($route->method === 'OPTIONS') {
                return true;
            }
        }

        return false;
    }

    /** @param array<int, mixed> $params */
    protected function matchRoute(
        DispatchContext $context,
        AbstractRoute $route,
        array &$params = [],
    ): bool {
        if ($route->match($context, $params)) {
            $context->route = $route;

            return true;
        }

        return false;
    }

    /** @param SplObjectStorage<AbstractRoute, array<int, mixed>> $matchedByPath */
    protected function routineMatch(SplObjectStorage $matchedByPath): DispatchContext|bool|null
    {
        assert($this->context !== null);
        $badRequest = false;

        foreach ([0, 1, 2] as $rank) {
            foreach ($matchedByPath as $route) {
                if ($this->getMethodMatchRank($route) !== $rank) {
                    continue;
                }

                /** @var array<int, mixed> $tempParams */
                $tempParams = $matchedByPath[$route];
                $this->context->clearResponseMeta();
                $this->context->route = $route;
                if ($route->matchRoutines($this->context, $tempParams)) {
                    return $this->configureContext(
                        $this->context,
                        $route,
                        static::cleanUpParams($tempParams),
                    );
                }

                $badRequest = true;
            }
        }

        return $badRequest ? false : null;
    }

    protected function sortRoutesByComplexity(): void
    {
        usort(
            $this->routes,
            static function (AbstractRoute $a, AbstractRoute $b): int {
                $pa = $a->pattern;
                $pb = $b->pattern;

                if ($pa === $pb) {
                    return 0;
                }

                $slashCount = Router::compareOcurrences($pa, $pb, '/');

                $aCatchall = preg_match('#/\*\*$#', $pa);
                $bCatchall = preg_match('#/\*\*$#', $pb);
                if ($aCatchall != $bCatchall) {
                    return $aCatchall ? 1 : -1;
                }

                if ($aCatchall && $bCatchall) {
                    return $slashCount ? 1 : -1;
                }

                if (Router::compareOcurrences($pa, $pb, AbstractRoute::PARAM_IDENTIFIER)) {
                    return -1;
                }

                return $slashCount ? -1 : 1;
            },
        );
    }

    public function __destruct()
    {
        if (!$this->isAutoDispatched || !$this->context) {
            return;
        }

        $response = $this->context->response();
        if ($response === null) {
            return;
        }

        echo (string) $response->getBody();
    }

    public function __toString(): string
    {
        $string = '';
        try {
            $response = $this->context?->response();
            if ($response !== null) {
                $string = (string) $response->getBody();
            }
        } catch (Throwable $exception) {
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }

        return $string;
    }

    /** @param array<int, mixed> $args */
    public function __call(string $method, array $args): AbstractRoute
    {
        if (count($args) < 2) {
            throw new InvalidArgumentException(
                'Any route binding must have at least 2 arguments',
            );
        }

        [$path, $routeTarget] = $args;

        if (is_array($path)) {
            $lastPath = array_pop($path);
            foreach ($path as $p) {
                $this->$method($p, $routeTarget);
            }

            return $this->$method($lastPath, $routeTarget);
        }

        if (is_callable($routeTarget)) {
            if (!isset($args[2])) {
                return $this->callbackRoute($method, $path, $routeTarget);
            }

            return $this->callbackRoute($method, $path, $routeTarget, $args[2]);
        }

        if ($routeTarget instanceof Routable) {
            return $this->instanceRoute($method, $path, $routeTarget);
        }

        if (!is_string($routeTarget)) {
            return $this->staticRoute($method, $path, $routeTarget);
        }

        if (!class_exists($routeTarget) && !interface_exists($routeTarget)) {
            return $this->staticRoute($method, $path, $routeTarget);
        }

        if (!isset($args[2])) {
            return $this->classRoute($method, $path, $routeTarget);
        }

        if (is_callable($args[2])) {
            return $this->factoryRoute($method, $path, $routeTarget, $args[2]);
        }

        return $this->classRoute($method, $path, $routeTarget, $args[2]);
    }
}
