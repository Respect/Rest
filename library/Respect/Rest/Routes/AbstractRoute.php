<?php

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;

abstract class AbstractRoute
{
    const PARAM_IDENTIFIER = '/*';
    const QUOTED_PARAM_IDENTIFIER = '/\*';
    const CATCHALL_IDENTIFIER = '/**';
    const REGEX_CATCHALL = '(/.*)?';
    const REGEX_SINGLE_PARAM = '/([^/]+)';
    const REGEX_TWO_ENDING_PARAMS = '/([^/]+)/([^/]+)';
    const REGEX_TWO_OPTIONAL_ENDING_PARAMS = '/([^/]+)(?:/([^/]+))?';
    const REGEX_TWO_MIXED_PARAMS = '(?:/([^/]+))?/([^/]+)';
    const REGEX_TWO_OPTIONAL_PARAMS = '(?:/([^/]+))?(?:/([^/]+))?';

    protected $matchPattern;
    protected $replacePattern;
    protected $method;
    protected $path;
    protected $reflection;
    protected $conditions = array();
    protected $preProxies = array();
    protected $postProxies = array();
    protected $dispatchedMethod = null;
    protected $dispatchedParams = array();
    protected $dispatched = false;

    public function __construct($method, $path)
    {
        $this->path = $path;
        $this->method = strtoupper($method);
        list($this->matchPattern, $this->replacePattern)
            = $this->createRegexPatterns($path);
    }

    abstract protected function runTarget($method, &$params);

    abstract protected function getReflection($method);

    public function reset()
    {
        $this->dispatchedMethod = null;
        $this->dispatchedParams = array();
        $this->dispatched = false;
    }

    public function configure($method, array $params=array())
    {
        $this->dispatchedMethod = $method;
        $this->dispatchedParams = $params;
        $this->dispatched = true;
        return true;
    }

    public function run()
    {
        $method = $this->dispatchedMethod;
        $params = $this->dispatchedParams;
        foreach ($this->preProxies as $preProxy)
            if (false === $this->paramSyncCall($method, $preProxy, $params))
                return false;

        $response = $this->runTarget($method, $params);

        foreach ($this->postProxies as $postProxy) {
            $proxyResponse = $this->paramSyncCall($method, $postProxy, $params);

            if (is_callable($proxyResponse))
                $response = $proxyResponse($response);

            if (false === $proxyResponse)
                return $response;
        }
        $this->reset();
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

    public function getPath()
    {
        return $this->path;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getMatchPattern()
    {
        return $this->matchPattern;
    }

    public function getDispatchedMethod()
    {
        return $this->dispatchedMethod;
    }

    public function getDispatchedParams()
    {
        return $this->dispatchedParams;
    }

    public function getDispatched()
    {
        return $this->dispatched;
    }

    public function match($uri, $method, &$params=array())
    {
        if (($method !== $this->method && $this->method !== 'ANY')
            || 0 === stripos($method, '__'))
            return false;

        foreach ($this->conditions as $cond)
            if (!$this->paramSyncCall($method, $cond, $params))
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

    protected function paramSyncCall($method, $callback, &$params)
    {
        $callbackReflection = $this->getCallbackReflection($callback);

        $reflection = $this->getReflection($method);

        $cbParams = array();

        foreach ($callbackReflection->getParameters() as $p)
            $cbParams[] = $this->extractParam($reflection, $p, $params);

        return call_user_func_array($callback, $cbParams);
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

    protected function getCallbackReflection($callback)
    {
        if (is_array($callback))
            return new ReflectionMethod($callback[0], $callback[1]);
        else
            return new ReflectionFunction($callback);
    }

    //turn sequenced parameters optional, so /*/*/* will match /1/2/3, /1/2 and /1
    protected function fixOptionalParams($pathQuoted)
    {
        if (strlen($pathQuoted) - strlen(static::REGEX_TWO_ENDING_PARAMS)
            === strripos($pathQuoted, static::REGEX_TWO_ENDING_PARAMS))
            $pathQuoted = str_replace(
                array(
                static::REGEX_TWO_ENDING_PARAMS,
                static::REGEX_TWO_MIXED_PARAMS
                ),
                array(
                static::REGEX_TWO_OPTIONAL_ENDING_PARAMS,
                static::REGEX_TWO_OPTIONAL_PARAMS
                ), $pathQuoted
            );

        return $pathQuoted;
    }

    protected function createRegexPatterns($path)
    {
        $path = rtrim($path, ' /');
        $extra = $this->extractCatchAllPattern($path);
        $matchPattern = str_replace(
            static::QUOTED_PARAM_IDENTIFIER, static::REGEX_SINGLE_PARAM,
            preg_quote($path), $paramCount
        );
        $replacePattern = str_replace(static::PARAM_IDENTIFIER, '/%s', $path);
        $matchPattern = $this->fixOptionalParams($matchPattern);
        $matchRegex = "#^{$matchPattern}{$extra}$#";
        return array($matchRegex, $replacePattern);
    }

    protected function extractCatchAllPattern(&$path)
    {
        $extra = static::REGEX_CATCHALL;

        if ((strlen($path) - strlen(static::CATCHALL_IDENTIFIER))
            === strripos($path, static::CATCHALL_IDENTIFIER))
            $path = substr($path, 0, -3);
        else
            $extra = '';

        $path = str_replace(
            static::CATCHALL_IDENTIFIER, static::PARAM_IDENTIFIER, $path
        );
        return $extra;
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