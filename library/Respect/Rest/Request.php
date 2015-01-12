<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest;

use ReflectionFunctionAbstract;
use ReflectionParameter;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\Routinable;
use Respect\Rest\Routines\ProxyableBy;
use Respect\Rest\Routines\ProxyableThrough;
use Respect\Rest\Routines\ParamSynced;

/** A routed HTTP Request */
class Request
{
    /** @var string The HTTP method (commonly GET, POST, PUT, DELETE, HEAD) */
    public $method = '';

    /**
     * @var array A numeric array containing valid URL parameters. For a route
     * path like /users/*, a Request for /users/alganet should have an array
     * equivalent to ['alganet']
     */
    public $params = array();

    /** @var AbstractRoute A route matched for this request */
    public $route;

    /** @var string The called URI */
    public $uri = '';

    /**
     * @param string $method The HTTP method
     * @param string $uri    The called URI
     */
    public function __construct($method = null, $uri = null)
    {
        //Tries to infer request variables only if null
        if (is_null($method)) {
            $method = isset($_SERVER['REQUEST_METHOD'])
                ? $_SERVER['REQUEST_METHOD']
                : 'GET';
        }

        if (is_null($uri)) {
            $uri = isset($_SERVER['REQUEST_URI'])
                ? $_SERVER['REQUEST_URI']
                : '/';
        }

        $uri = parse_url($uri, PHP_URL_PATH);
        $this->uri = rtrim($uri, ' /');       //We always ignore the last /
        $this->method = strtoupper($method);  //normalizing the HTTP method
    }

    /**
     * Converting this request to string dispatch
     */
    public function __toString()
    {
        return $this->response();
    }

    /**
     * Declares an error handler for a single Router::errorRoute instance on the
     * fly before dispatching the request, so the application can capture the
     * errors. These are cleaned after dispatching by forwardErrors()
     *
     * @see    Respect\Rest\Request::forwardErrors
     * @see    http://php.net/set_error_handler
     *
     * @return mixed The previous error handler
     */
    protected function prepareForErrorForwards()
    {
        $errorHandler = null;

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

    /**
     * Iterates over routines to find instances of
     * Respect\Rest\Routines\ProxyableBy and call them, forwarding if
     * necessary
     *
     * @see    Respect\Rest\Routines\ProxyableBy
     * @see    Respect\Rest\Request::routineCall
     *
     * @return mixed A route forwarding or false
     */
    protected function processPreRoutines()
    {
        foreach ($this->route->routines as $routine) {
            if (!$routine instanceof ProxyableBy) {
                continue;
            } else {
                $result = $this->routineCall(
                    'by',
                    $this->method,
                    $routine,
                    $this->params
                );

                //Routine returned an instance, let's forward it
                if ($result instanceof AbstractRoute) {
                    return $this->forward($result);
                } elseif (false === $result) {
                    return false;
                }
            }
        }
    }

    /**
     * Iterates over routines to find instances of
     * Respect\Rest\Routines\ProxyableThrough and call them, forwarding if
     * necessary
     *
     * @see    Respect\Rest\Routines\ProxyableThrough
     * @see    Respect\Rest\Request::routineCall
     *
     * @return mixed A route forwarding or false
     */
    protected function processPosRoutines($response)
    {
        $proxyResults = array();

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

        //Some routine returned a callback as its result. Let's run
        //these callbacks on our actual response.
        //This is mainly used by accept() and similar ones.
        foreach ($proxyResults as $proxyCallback) {
            if (is_callable($proxyCallback)) {
                $response = call_user_func_array(
                    $proxyCallback,
                    array($response)
                );
            }
        }

        return $response;
    }

    /**
     * Restores the previous error handler if present then check error routes
     * for logged errors, forwarding them or returning null silently
     *
     * @param mixed $errorHandler Some error handler (internal or external to
     *                            Respect)
     *
     * @return mixed A route forwarding or a silent null
     */
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

    /**
     * Does a catch-like operation on an exception based on previously
     * declared instances from Router::exceptionRoute
     *
     * @param Exception $e Any exception
     *
     * @return mixed A route forwarding or a silent null
     */
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

    /**
     * Generates and returns the response from the current route
     *
     * @return string A response!
     */
    public function response()
    {
        try {
            //No routes, get out
            if (!$this->route instanceof AbstractRoute) {
                return;
            }

            $errorHandler = $this->prepareForErrorForwards();
            $preRoutineResult = $this->processPreRoutines();

            if (!is_null($preRoutineResult)) {
                return $preRoutineResult;
            }

            $response = $this->route->runTarget($this->method, $this->params);

            //The code returned another route, this is a forward
            if ($response instanceof AbstractRoute) {
                return $this->forward($response);
            }

            $possiblyModifiedResponse = $this->processPosRoutines($response);
            $errorResponse = $this->forwardErrors($errorHandler);

            if (!is_null($errorResponse)) {
                return $errorResponse;
            }

            return $possiblyModifiedResponse;
        } catch (\Exception $e) {
            //Tries to catch it using catchExceptions()
            if (!$exceptionResponse = $this->catchExceptions($e)) {
                throw $e;
            }

            //Returns whatever the exception routes returned
            return (string) $exceptionResponse;
        }
    }

    /**
     * Calls a routine on the current route and returns its result
     *
     * @param string     $type    The name of the routine (accept, when, etc)
     * @param string     $method  The method name (GET, HEAD, POST, etc)
     * @param Routinable $routine Some routine instance
     * @param array      $params  Params from the routine
     *
     * @return mixed Whatever the routine returns
     */
    public function routineCall($type, $method, Routinable $routine, &$params)
    {
        $reflection = $this->route->getReflection(
            //GET and HEAD are the same for routines
            $method == 'HEAD' ? 'GET' : $method
        );

        $callbackParameters = array();

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

    /**
     * Extracts a parameter value from the current route
     *
     * @param ReflectionFunctionAbstract $callback   Any function reflection
     * @param ReflectionParameter        $routeParam Any parameter reflection
     * @param array                      $params     Request URI params
     *
     * @return mixed a value from the reflected param
     */
    protected function extractRouteParam(
        ReflectionFunctionAbstract $callback,
        ReflectionParameter $routeParam,
        &$params
    ) {
        foreach ($callback->getParameters() as $callbackParamReflection) {
            //Check if parameters have same name and present (not filtered)
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

        return;
    }

    /**
     * Forwards a route
     *
     * @param AbstractRoute $route Any route
     *
     * @return Response from the forwarded route
     */
    public function forward(AbstractRoute $route)
    {
        $this->route = $route;

        return $this->response();
    }
}
