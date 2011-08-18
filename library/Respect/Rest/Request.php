<?php

namespace Respect\Rest;

use ArrayAccess;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use RuntimeException;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\AbstractRoutine;
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

    /**
     * Generates and returns the response from the current route
     */
    public function response()
    {
        if (!$this->route)
            throw new RuntimeException('No route set');

        foreach ($this->route->routines as $routine)
            if ($routine instanceof ProxyableBy
                && false === $this->routineCall('by', $this->method, $routine,
                    $this->params))
                return false;

        $response = $this->route->runTarget($this->method, $this->params);
        $proxyResult = false;

        foreach ($this->route->routines as $routine)
            if ($routine instanceof ProxyableThrough)
                $proxyResult = $this->routineCall('through', $this->method,
                        $routine, $this->params);

        if (is_callable($proxyResult))
            $response = $proxyResult($response);

        if (false === $proxyResult)
            return $response;

        return $response;
    }

    /**
     * Calls a routine on the current route and returns its result
     */
    public function routineCall($type, $method, AbstractRoutine $routine, &$routeParamsValues)
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

    /**
     * Extracts a parameter value from the current route
     */
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

}

/**
 * LICENSE
 *
 * Copyright (c) 2009-2011, Alexandre Gomes Gaigalas.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *     * Neither the name of Alexandre Gomes Gaigalas nor the names of its
 *       contributors may be used to endorse or promote products derived from this
 *       software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */