<?php

namespace Respect\Rest;

use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

class Route
{

    protected $matchPattern;
    protected $replacePattern;
    protected $method;
    protected $callback;
    protected $reflection;
    protected $conditions = array();
    protected $preProxies = array();
    protected $postProxies = array();

    public function __construct($method, $callback, $matchPattern, $replacePattern)
    {
        $this->method = $method;
        $this->callback = $callback;
        $this->matchPattern = $matchPattern;
        $this->replacePattern = $replacePattern;
    }

    public function __invoke($param1=null, $etc=null)
    {
        $params = func_get_args();

        foreach ($this->preProxies as $preProxy)
            if (false === $this->paramSyncCall($preProxy, $params))
                return false;

        $response = call_user_func_array($this->callback, $params);

        foreach ($this->postProxies as $postProxy)
            if (false === $this->paramSyncCall($postProxy, $params))
                return $response;

        return $response;
    }

    public function createUri($param1=null, $etc=null)
    {
        $params = func_get_args();
        array_unshift($params, $this->replacePattern);
        return call_user_func_array(
            'sprintf', $params
        );
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getRegex()
    {
        return $this->matchPattern;
    }

    public function match($uri, &$params=array())
    {
        foreach ($this->conditions as $cond)
            if (!$this->paramSyncCall($cond, $params))
                return false;

        return preg_match($this->matchPattern, $uri, $params);
    }

    public function by($proxy1=null, $etc=null)
    {
        $this->preProxies = $this->checkCallbackParams(
                func_get_args(), 'Route proxies must be callable'
        );
        return $this;
    }

    public function when($condition1=null, $etc=null)
    {
        $this->conditions = $this->checkCallbackParams(
                func_get_args(), 'Route conditions must be callable'
        );
        return $this;
    }

    public function then($proxy1=null, $etc=null)
    {
        $this->postProxies = $this->checkCallbackParams(
                func_get_args(), 'Route proxies must be callable'
        );
        return $this;
    }

    protected function checkCallbackParams($params, $message)
    {
        if (!array_filter($params, 'is_callable'))
            throw new InvalidArgumentException($message);
        return $params;
    }

    protected function paramSyncCall($callback, &$params)
    {
        $callbackReflection = $this->getCallbackReflection($callback);

        if (!isset($this->reflection))
            $this->reflection = $this->getCallbackReflection($this->callback);

        $cbParams = array();

        foreach ($callbackReflection->getParameters() as $p)
            $cbParams[] = $this->extractParam($this->reflection, $p, $params);

        return call_user_func_array($callback, $cbParams);
    }

    protected function extractParam(ReflectionFunctionAbstract $callbackR, ReflectionParameter $cbParam, &$params)
    {
        foreach ($callbackR->getParameters() as $callbackParam)
            if ($callbackParam->getName() === $cbParam->getName()
                && isset($params[$callbackParam->getPosition()]))
                return $params[$callbackParam->getPosition()];

        if ($cbParam->isDefaultValueAvailable())
            return $cbParam->getDefaultValue();

        return null;
    }

    protected function getCallbackReflection($callback)
    {
        if (is_array($callback))
            return new ReflectionMethod($callback[0], $callback[1]);
        else
            return new ReflectionFunction($callback);
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