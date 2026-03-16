<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\Routinable;
use Respect\Rest\Routines\ProxyableBy;
use Respect\Rest\Routines\ProxyableThrough;
use Respect\Rest\Routines\ParamSynced;

/** A routed HTTP Request — internal routing context wrapping a PSR-7 server request */
class Request
{
    /** @var string The HTTP method (commonly GET, POST, PUT, DELETE, HEAD) */
    public $method = '';

    /**
     * @var array A numeric array containing valid URL parameters. For a route
     * path like /users/*, a Request for /users/alganet should have an array
     * equivalent to ['alganet']
     */
    public $params = [];

    /** @var AbstractRoute A route matched for this request */
    public $route;

    /** @var string The called URI */
    public $uri = '';

    /** @var ServerRequestInterface The wrapped PSR-7 server request */
    public $serverRequest;

    public function __construct(ServerRequestInterface $serverRequest)
    {
        $this->serverRequest = $serverRequest;
        $this->uri = rtrim(rawurldecode($serverRequest->getUri()->getPath()), ' /');
        $this->method = strtoupper($serverRequest->getMethod());
    }

    public function __toString()
    {
        $response = $this->response();

        if ($response === null) {
            return '';
        }

        return (string) $response->getBody();
    }

    protected function prepareForErrorForwards()
    {
       foreach ($this->route->sideRoutes as $sideRoute) {
            if ($sideRoute instanceof Routes\Error) {
                return set_error_handler(
                    function () use ($sideRoute) {
                        $sideRoute->errors[] = func_get_args();
                    }
                );
            }
        }
    }

    protected function processPreRoutines()
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
            } elseif (false === $result) {
                return false;
            }
        }
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

    protected function forwardErrors($errorHandler)
    {
        if (isset($errorHandler)) {
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
    }

    protected function catchExceptions($e)
    {
        foreach ($this->route->sideRoutes as $sideRoute) {
            $exceptionClass = get_class($e);
            if (
                $exceptionClass      === $sideRoute->class
                || $sideRoute->class === 'Exception'
                || $sideRoute->class === '\Exception'
            ) {
                $sideRoute->exception = $e;

                return $this->forward($sideRoute);
            }
        }
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
                if ($errorResponse instanceof ResponseInterface) {
                    return $errorResponse;
                }
                return $this->route->wrapResponse($errorResponse);
            }

            return $this->route->wrapResponse($processedResult);
        } catch (\Exception $e) {
            if (!$exceptionResponse = $this->catchExceptions($e)) {
                throw $e;
            }

            if ($exceptionResponse instanceof ResponseInterface) {
                return $exceptionResponse;
            }

            return $this->route->wrapResponse($exceptionResponse);
        }
    }

    public function routineCall($type, $method, Routinable $routine, &$params)
    {
        $reflection = $this->route->getReflection(
            $method == 'HEAD' ? 'GET' : $method
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

    protected function extractRouteParam(
        ReflectionFunctionAbstract $callback,
        ReflectionParameter $routeParam,
        &$params
    ) {
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

    public function forward(AbstractRoute $route)
    {
        $this->route = $route;

        return $this->response();
    }
}
