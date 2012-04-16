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
        $uri = $uri ? : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->uri = rtrim($uri, ' /');
        $this->method = strtoupper($method ? : $_SERVER['REQUEST_METHOD']);
    }

    public function __toString()
    {
        return $this->response();
    }

    /** Generates and returns the response from the current route */
    public function response()
    {
        if (!$this->route)
            return null;

        foreach ($this->route->routines as $routine)
            if (!$routine instanceof ProxyableBy)
                continue;
            elseif (false === $result = $this->routineCall('by', $this->method, $routine, $this->params))
                    return false;
            elseif ($result instanceof AbstractRoute)
                    return $this->forward($result);

        $response = $this->route->runTarget($this->method, $this->params);
        
        if ($response instanceof AbstractRoute)
            return $this->forward($response);
        
        $proxyResults = array();

        foreach ($this->route->routines as $routine)
            if ($routine instanceof ProxyableThrough)
                $proxyResults[] = $this->routineCall('through', $this->method,
                        $routine, $this->params);

        if (!empty($proxyResults))
            foreach ($proxyResults as $proxyCallback)
                if (is_callable($proxyCallback))
                    $response = $proxyCallback($response);

        return $response;
    }

    /** Calls a routine on the current route and returns its result */
    public function routineCall($type, $method, Routinable $routine, &$routeParamsValues)
    {
        $reflection = $this->route->getReflection($method);

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
    
    protected function forward(AbstractRoute $route) 
    {
        $this->route = $route;
        return $this->response();
    }

}
