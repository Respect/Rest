<?php

declare(strict_types=1);

namespace Respect\Rest;

use InvalidArgumentException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;
use Respect\Rest\Routes\AbstractRoute;
use Throwable;

use function array_pop;
use function class_exists;
use function count;
use function interface_exists;
use function is_array;
use function is_callable;
use function is_string;
use function preg_match;
use function rtrim;
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
final class Router implements MiddlewareInterface, RequestHandlerInterface, RouteProvider
{
    public DispatchContext|null $context = null;

    /** @var array<int, Routines\Routinable> */
    protected array $globalRoutines = [];

    /** @var array<int, AbstractRoute> */
    protected array $routes = [];

    /** @var array<int, AbstractRoute> */
    protected array $sideRoutes = [];

    private DispatchEngine|null $dispatchEngine = null;

    public function __construct(
        protected string $basePath,
        private ResponseFactoryInterface&StreamFactoryInterface $factory,
    ) {
        $this->basePath = rtrim($basePath, '/');
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
        $route->basePath = $this->basePath;

        foreach ($this->globalRoutines as $routine) {
            $route->appendRoutine($routine);
        }

        $this->sortRoutesByComplexity();

        return $this;
    }

    public function appendSideRoute(AbstractRoute $route): static
    {
        $this->sideRoutes[] = $route;

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
        return $this->dispatchEngine()->dispatch($serverRequest);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->dispatchEngine()->handle($request);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $context = $this->dispatch($request);

        if ($context->route === null && !$context->hasPreparedResponse()) {
            return $handler->handle($request);
        }

        if ($context->route === null) {
            $response = $context->response();
            if ($response !== null && $response->getStatusCode() === 404) {
                return $handler->handle($request);
            }

            return $response ?? $handler->handle($request);
        }

        return $context->response() ?? $handler->handle($request);
    }

    public function createDispatchContext(ServerRequestInterface $serverRequest): DispatchContext
    {
        return new DispatchContext(
            $serverRequest,
            $this->factory,
        );
    }

    public function dispatchContext(DispatchContext $context): DispatchContext
    {
        return $this->dispatchEngine()->dispatchContext($context);
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

    public function statusRoute(int|null $statusCode, callable $callback): Routes\Status
    {
        $route = new Routes\Status($statusCode, $callback);
        $this->appendSideRoute($route);

        return $route;
    }

    /** @return array<int, Routes\AbstractRoute> */
    public function getSideRoutes(): array
    {
        return $this->sideRoutes;
    }

    public function factoryRoute(string $method, string $path, string $className, callable $factory): Routes\Factory
    {
        $route = new Routes\Factory($method, $path, $className, $factory);
        $this->appendRoute($route);

        return $route;
    }

    public function instanceRoute(string $method, string $path, object $instance): Routes\Instance
    {
        $route = new Routes\Instance($method, $path, $instance);
        $this->appendRoute($route);

        return $route;
    }

    public function staticRoute(string $method, string $path, mixed $staticValue): Routes\StaticValue
    {
        $route = new Routes\StaticValue($method, $path, $staticValue);
        $this->appendRoute($route);

        return $route;
    }

    /** @return array<int, AbstractRoute> */
    public function getRoutes(): array
    {
        return $this->routes;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function dispatchEngine(): DispatchEngine
    {
        return $this->dispatchEngine ??= new DispatchEngine(
            $this,
            $this->factory,
            function (DispatchContext $ctx): void {
                $this->context = $ctx;
            },
        );
    }

    public static function compareOcurrences(string $patternA, string $patternB, string $sub): bool
    {
        return substr_count($patternA, $sub) < substr_count($patternB, $sub);
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
