<?php

namespace Respect\Rest;

use ArrayAccess;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use RuntimeException;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\Routinable;
use Respect\Rest\Routines\ProxyableBy;
use Respect\Rest\Routines\ProxyableThrough;
use Respect\Rest\Routines\ProxyableWhen;
use Respect\Rest\Routines\ParamSynced;

/** A routed HTTP Request */
class Request
{

    public $method = '';
    public $params = array();
    /** @var AbstractRoute */
    public $route;
    public $uri = '';

    public function __construct($method=null, $uri=null)
    {
        if (is_null($method)) {
            $method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET';
        }
        if (is_null($uri)) {
            $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
        }
        $uri = parse_url($uri, PHP_URL_PATH);
        $this->uri = rtrim($uri, ' /');
        $this->method = strtoupper($method);
    }

    public function __toString()
    {
        return $this->response();
    }

    protected function prepareForErrorForwards()
    {
        $errorHandler = null;
        foreach ($this->route->sideRoutes as $sideRoute) {
            if ($sideRoute instanceof Routes\Error) {
                $errorHandler = set_error_handler(function() use ($sideRoute) {
                    $sideRoute->errors[] = func_get_args();
                }) ?: false;
                break;
            }
        }
        return $errorHandler;
    }

    protected function processPreRoutines()
    {
        foreach ($this->route->routines as $routine)
            if (!$routine instanceof ProxyableBy)
                continue;
            elseif (false === $result = $this->routineCall('by', $this->method, $routine, $this->params))
                    return false;
            elseif ($result instanceof AbstractRoute)
                    return $this->forward($result);
    }

    protected function processPosRoutines($response)
    {
        $proxyResults = array();

        foreach ($this->route->routines as $routine)
            if ($routine instanceof ProxyableThrough)
                $proxyResults[] = $this->routineCall('through', $this->method,
                        $routine, $this->params);

        if (!empty($proxyResults))
            foreach ($proxyResults as $proxyCallback)
                if (is_callable($proxyCallback))
                    $response = call_user_func_array($proxyCallback, array($response)); //$proxyCallback($response);

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
            if ($sideRoute instanceof Routes\Error
                && $sideRoute->errors) {
                return $this->forward($sideRoute);
            }
        }
    }

    protected function catchExceptions($e)
    {
        foreach ($this->route->sideRoutes as $sideRoute) {
            if ($eClass = get_class($e) === $sideRoute->class
                || $sideRoute->class === 'Exception'
                || $sideRoute->class === '\Exception') {
                $sideRoute->exception = $e;
                return $this->forward($sideRoute);
            }
        }
    }

    /** Generates and returns the response from the current route */
    public function response()
    {
        try {
            if (!$this->route instanceof AbstractRoute)
                return null;

            $errorHandler = $this->prepareForErrorForwards();

            $result = $this->processPreRoutines();
            if (!is_null($result)) {
                return $result;
            }

            $response = $this->route->runTarget($this->method, $this->params);
            if ($response instanceof AbstractRoute) {
                return $this->forward($response);
            }
            $response = $this->processPosRoutines($response);
            
            $result = $this->forwardErrors($errorHandler);
            if (!is_null($result)) {
                return $result;
            }

            return $response;
        } catch (\Exception $e) {
            if (!$caught = $this->catchExceptions($e)) {
                throw $e;
            }
            return $caught;
        }
    }

    /** Calls a routine on the current route and returns its result */
    public function routineCall($type, $method, Routinable $routine, &$routeParamsValues)
    {
        $reflection = $this->route->getReflection($method == 'HEAD' ? 'GET' : $method);

        $callbackParameters = array();

        if ($routine instanceof ParamSynced)
            foreach ($routine->getParameters() as $parameter)
                $callbackParameters[] = $this->extractRouteParam($reflection,
                        $parameter, $routeParamsValues);
        else
            $callbackParameters = $routeParamsValues;

        return $routine->{$type}($this, $callbackParameters);
    }

    /** Extracts a parameter value from the current route */
    protected function extractRouteParam(ReflectionFunctionAbstract $callback, ReflectionParameter $routeParam, &$routeParamsValues)
    {
        foreach ($callback->getParameters() as $callbackParamReflection)
            if ($callbackParamReflection->getName() === $routeParam->getName()
                && isset($routeParamsValues[$callbackParamReflection->getPosition()]))
                return $routeParamsValues[$callbackParamReflection->getPosition()];

        if ($routeParam->isDefaultValueAvailable())
            return $routeParam->getDefaultValue();

        return null;
    }

    public function forward(AbstractRoute $route)
    {
        $this->route = $route;
        return $this->response();
    }

}
