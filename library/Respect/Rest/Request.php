<?php

namespace Respect\Rest;

use ReflectionFunctionAbstract;
use ReflectionParameter;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\AbstractRoutine;
use Respect\Rest\Routines\ProxyableBy;
use Respect\Rest\Routines\ProxyableThrough;
use Respect\Rest\Routines\ProxyableWhen;
use Respect\Rest\Routines\ParamSynced;

class Request
{

    protected $route;
    protected $method;
    protected $uri;
    protected $params = array();

    public function __construct(AbstractRoute $route, $uri, $method,
        array $params)
    {
        $this->route = $route;
        $this->uri = $uri;
        $this->method = $method;
        $this->params = $params;
    }

    public function getRoute()
    {
        return $this->route;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getUri()
    {
        return $this->uri;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function response()
    {
        foreach ($this->route->getRoutines() as $r)
            if ($r instanceof ProxyableBy
                && false === $this->syncCall('by', $this->method, $r,
                    $this->params))
                return false;

        $response = $this->route->runTarget($this->method, $this->params);
        $proxyResult = false;

        foreach ($this->route->getRoutines() as $r)
            if ($r instanceof ProxyableThrough)
                $proxyResult = $this->syncCall('through', $this->method, $r,
                        $this->params);

        if (is_callable($proxyResult))
            $response = $proxyResult($response);

        if (false === $proxyResult)
            return $response;

        return $response;
    }

    protected function syncCall($op, $method, AbstractRoutine $routine, &$params)
    {
        $reflection = $this->route->getReflection($method);

        $cbParams = array();

        if ($routine instanceof ParamSynced)
            foreach ($routine->getParameters() as $p)
                $cbParams[] = $this->extractParam($reflection, $p, $params); else
            $cbParams = $params;

        return $routine->{$op}($this, $cbParams);
    }

    protected function extractParam(ReflectionFunctionAbstract $callbackR,
        ReflectionParameter $cbParam, &$params)
    {
        foreach ($callbackR->getParameters() as $callbackParam)
            if ($callbackParam->getName() === $cbParam->getName()
                && isset($params[$callbackParam->getPosition()]))
                return $params[$callbackParam->getPosition()];

        if ($cbParam->isDefaultValueAvailable())
            return $cbParam->getDefaultValue();

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