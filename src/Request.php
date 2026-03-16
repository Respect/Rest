<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\ParamSynced;
use Respect\Rest\Routines\ProxyableBy;
use Respect\Rest\Routines\ProxyableThrough;
use Respect\Rest\Routines\Routinable;

/** Internal routing context wrapping a PSR-7 server request */
class Request
{
    public string $method = '';

    /** @var array<int, mixed> */
    public array $params = [];

    public ?AbstractRoute $route = null;

    public string $uri = '';

    public ServerRequestInterface $serverRequest;

    public function __construct(ServerRequestInterface $serverRequest)
    {
        $this->serverRequest = $serverRequest;
        $this->uri = rtrim(rawurldecode($serverRequest->getUri()->getPath()), ' /');
        $this->method = strtoupper($serverRequest->getMethod());
    }

    public function __toString(): string
    {
        $response = $this->response();

        if ($response === null) {
            return '';
        }

        return (string) $response->getBody();
    }

    /** @return callable|null The previous error handler, or null */
    protected function prepareForErrorForwards(): mixed
    {
        foreach ($this->route->sideRoutes as $sideRoute) {
            if ($sideRoute instanceof Routes\Error) {
                return set_error_handler(
                    static function () use ($sideRoute): void {
                        $sideRoute->errors[] = func_get_args();
                    }
                );
            }
        }

        return null;
    }

    protected function processPreRoutines(): mixed
    {
        foreach ($this->route->routines as $routine) {
            if (!$routine instanceof ProxyableBy) {
                continue;
            }

            $result = $this->routineCall(
                'by',
                $this->method,
                $routine,
                $this->params
            );

            if ($result instanceof AbstractRoute) {
                return $this->forward($result);
            }

            if (false === $result) {
                return false;
            }
        }

        return null;
    }

    /**
     * Processes post-routines on the raw handler result.
     * Routines still receive raw values (arrays, strings, etc.) — not ResponseInterface.
     */
    protected function processPosRoutines(mixed $response): mixed
    {
        $proxyResults = [];

        foreach ($this->route->routines as $routine) {
            if ($routine instanceof ProxyableThrough) {
                $proxyResults[] = $this->routineCall(
                    'through',
                    $this->method,
                    $routine,
                    $this->params
                );
            }
        }

        foreach ($proxyResults as $proxyCallback) {
            if (is_callable($proxyCallback)) {
                $response = $proxyCallback($response);
            }
        }

        return $response;
    }

    protected function forwardErrors(mixed $errorHandler): ?ResponseInterface
    {
        if ($errorHandler !== null) {
            if (!$errorHandler) {
                restore_error_handler();
            } else {
                set_error_handler($errorHandler);
            }
        }

        foreach ($this->route->sideRoutes as $sideRoute) {
            if ($sideRoute instanceof Routes\Error && $sideRoute->errors) {
                return $this->forward($sideRoute);
            }
        }

        return null;
    }

    protected function catchExceptions(\Throwable $e): ?ResponseInterface
    {
        foreach ($this->route->sideRoutes as $sideRoute) {
            if (!$sideRoute instanceof Routes\Exception) {
                continue;
            }

            $exceptionClass = get_class($e);
            if (
                $exceptionClass === $sideRoute->class
                || $sideRoute->class === 'Exception'
                || $sideRoute->class === '\Exception'
            ) {
                $sideRoute->exception = $e;

                return $this->forward($sideRoute);
            }
        }

        return null;
    }

    /** Generates the PSR-7 response from the current route */
    public function response(): ?ResponseInterface
    {
        try {
            if (!$this->route instanceof AbstractRoute) {
                return null;
            }

            $errorHandler = $this->prepareForErrorForwards();
            $preRoutineResult = $this->processPreRoutines();

            if ($preRoutineResult !== null) {
                if ($preRoutineResult instanceof ResponseInterface) {
                    return $preRoutineResult;
                }
                if ($preRoutineResult === false) {
                    return $this->route->wrapResponse('');
                }
                return $this->route->wrapResponse($preRoutineResult);
            }

            $rawResult = $this->route->runTarget($this->method, $this->params);

            if ($rawResult instanceof AbstractRoute) {
                return $this->forward($rawResult);
            }

            $processedResult = $this->processPosRoutines($rawResult);
            $errorResponse = $this->forwardErrors($errorHandler);

            if ($errorResponse !== null) {
                return $errorResponse;
            }

            return $this->route->wrapResponse($processedResult);
        } catch (\Exception $e) {
            $exceptionResponse = $this->catchExceptions($e);
            if ($exceptionResponse === null) {
                throw $e;
            }

            return $exceptionResponse;
        }
    }

    /** @param array<int, mixed> $params */
    public function routineCall(string $type, string $method, Routinable $routine, array &$params): mixed
    {
        $reflection = $this->route->getReflection(
            $method === 'HEAD' ? 'GET' : $method
        );

        $callbackParameters = [];

        if (!$routine instanceof ParamSynced) {
            $callbackParameters = $params;
        } else {
            foreach ($routine->getParameters() as $parameter) {
                $callbackParameters[] = $this->extractRouteParam(
                    $reflection,
                    $parameter,
                    $params
                );
            }
        }

        return $routine->{$type}($this, $callbackParameters);
    }

    /** @param array<int, mixed> $params */
    protected function extractRouteParam(
        ReflectionFunctionAbstract $callback,
        ReflectionParameter $routeParam,
        array &$params,
    ): mixed {
        foreach ($callback->getParameters() as $callbackParamReflection) {
            if (
                $callbackParamReflection->getName() === $routeParam->getName()
                && isset($params[$callbackParamReflection->getPosition()])
            ) {
                return $params[$callbackParamReflection->getPosition()];
            }
        }

        if ($routeParam->isDefaultValueAvailable()) {
            return $routeParam->getDefaultValue();
        }

        return null;
    }

    public function forward(AbstractRoute $route): ?ResponseInterface
    {
        $this->route = $route;

        return $this->response();
    }
}
