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
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Fluent\Resolvers\Ucfirst;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\Routinable;

use function array_pop;
use function assert;
use function class_exists;
use function count;
use function interface_exists;
use function is_array;
use function is_callable;
use function is_string;
use function preg_match;
use function rtrim;
use function substr_count;
use function usort;

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
    /** @var array<int, Routines\Routinable> */
    protected array $globalRoutines = [];

    /** @var array<int, AbstractRoute> */
    protected array $routes = [];

    /** @var array<int, AbstractRoute> */
    protected array $handlers = [];

    private DispatchEngine|null $dispatchEngine = null;

    private NamespaceLookup $routineLookup;

    public function __construct(
        protected string $basePath,
        private ResponseFactoryInterface&StreamFactoryInterface $factory,
    ) {
        $this->basePath = rtrim($basePath, '/');
        $this->routineLookup = new NamespaceLookup(
            new Ucfirst(),
            Routinable::class,
            'Respect\\Rest\\Routines',
        );
    }

    public function always(string $routineName, mixed ...$params): static
    {
        $routineInstance = $this->routineLookup->create($routineName, $params);
        assert($routineInstance instanceof Routinable);
        $this->globalRoutines[] = $routineInstance;

        foreach ($this->routes as $route) {
            $route->appendRoutine($routineInstance);
        }

        return $this;
    }

    public function withRoutineNamespace(string $namespace): static
    {
        $this->routineLookup = $this->routineLookup->withNamespace($namespace);

        return $this;
    }

    public function appendRoute(AbstractRoute $route): static
    {
        $this->routes[] = $route;
        $route->basePath = $this->basePath;
        $route->setRoutineLookup($this->routineLookup);

        foreach ($this->globalRoutines as $routine) {
            $route->appendRoutine($routine);
        }

        $this->sortRoutesByComplexity();

        return $this;
    }

    public function appendHandler(AbstractRoute $handler): static
    {
        $this->handlers[] = $handler;
        $handler->setRoutineLookup($this->routineLookup);

        foreach ($this->globalRoutines as $routine) {
            $handler->appendRoutine($routine);
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
            $this->dispatchEngine()->routinePipeline(),
            $this->handlers,
            $this->basePath,
        );
    }

    public function dispatchContext(DispatchContext $context): DispatchContext
    {
        return $this->dispatchEngine()->dispatchContext($context);
    }

    public function onException(string $className, callable $callback): Handlers\ExceptionHandler
    {
        $handler = new Handlers\ExceptionHandler($className, $callback);
        $this->appendHandler($handler);

        return $handler;
    }

    public function onError(callable $callback): Handlers\ErrorHandler
    {
        $handler = new Handlers\ErrorHandler($callback);
        $this->appendHandler($handler);

        return $handler;
    }

    public function onStatus(int|null $statusCode, callable $callback): Handlers\StatusHandler
    {
        $handler = new Handlers\StatusHandler($statusCode, $callback);
        $this->appendHandler($handler);

        return $handler;
    }

    /** @return array<int, Routes\AbstractRoute> */
    public function getHandlers(): array
    {
        return $this->handlers;
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
