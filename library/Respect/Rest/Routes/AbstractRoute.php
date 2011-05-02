<?php

namespace Respect\Rest\Routes;

use ReflectionFunctionAbstract;
use ReflectionParameter;
use Respect\Rest\Routines\AbstractRoutine;
use Respect\Rest\Routines\By;
use Respect\Rest\Routines\Through;
use Respect\Rest\Routines\When;

abstract class AbstractRoute
{
    const CATCHALL_IDENTIFIER = '/**';
    const PARAM_IDENTIFIER = '/*';
    const QUOTED_PARAM_IDENTIFIER = '/\*';
    const REGEX_CATCHALL = '(/.*)?';
    const REGEX_SINGLE_PARAM = '/([^/]+)';
    const REGEX_TWO_ENDING_PARAMS = '/([^/]+)/([^/]+)';
    const REGEX_TWO_MIXED_PARAMS = '(?:/([^/]+))?/([^/]+)';
    const REGEX_TWO_OPTIONAL_ENDING_PARAMS = '/([^/]+)(?:/([^/]+))?';
    const REGEX_TWO_OPTIONAL_PARAMS = '(?:/([^/]+))?(?:/([^/]+))?';

    protected $dispatched = false;
    protected $dispatchedMethod = null;
    protected $dispatchedParams = array();
    protected $dispatchedEnv = array();
    protected $dispatchedGet = array();
    protected $dispatchedPost = array();
    protected $matchPattern;
    protected $method;
    protected $path;
    protected $reflection;
    protected $replacePattern;
    protected $routines = array();

    abstract protected function getReflection($method);

    abstract protected function runTarget($method, &$params);

    public function __construct($method, $path)
    {
        $this->path = $path;
        $this->method = strtoupper($method);
        list($this->matchPattern, $this->replacePattern)
            = $this->createRegexPatterns($path);
    }

    public function __call($method, $arguments)
    {
        $routineClass = 'Respect\\Rest\\Routines\\' . ucfirst($method);

        foreach ($arguments as $param)
            $this->appendRoutine(new $routineClass($param));

        return $this;
    }

    public function appendRoutine(AbstractRoutine $routine)
    {
        $this->routines[] = $routine;
    }

    public function configure($method, array $params=array())
    {
        $this->dispatchedMethod = $method;
        $this->dispatchedParams = $params;
        $this->dispatched = true;
        return true;
    }

    public function createUri($param1=null, $etc=null)
    {
        $params = func_get_args();
        array_unshift($params, $this->replacePattern);
        return call_user_func_array(
            'sprintf', $params
        );
    }

    public function getDispatched()
    {
        return $this->dispatched;
    }

    public function getDispatchedEnv()
    {
        return $this->dispatchedEnv;
    }

    public function getDispatchedGet()
    {
        return $this->dispatchedGet;
    }

    public function getDispatchedMethod()
    {
        return $this->dispatchedMethod;
    }

    public function getDispatchedParams()
    {
        return $this->dispatchedParams;
    }

    public function getDispatchedPost()
    {
        return $this->dispatchedPost;
    }

    public function getMatchPattern()
    {
        return $this->matchPattern;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function match($uri, $method, &$params=array())
    {
        if (($method !== $this->method && $this->method !== 'ANY')
            || 0 === stripos($method, '__'))
            return false;

        foreach ($this->routines as $r)
            if ($r instanceof When && !$this->syncCall($method, $r, $params))
                return false;

        if (!preg_match($this->matchPattern, $uri, $params))
            return false;

        if (count($params) > 1 && false !== stripos(end($params), '/')) {
            $lastParam = array_pop($params);
            $params = array_merge($params, explode('/', $lastParam));
        }

        return true;
    }

    public function reset()
    {
        $this->dispatchedMethod = null;
        $this->dispatchedParams = array();
        $this->dispatched = false;
    }

    public function run()
    {
        $method = $this->dispatchedMethod;
        $params = $this->dispatchedParams;

        foreach ($this->routines as $r)
            if ($r instanceof By
                && false === $this->syncCall($method, $r, $params))
                return false;

        $response = $this->runTarget($method, $params);
        $proxyResponse = false;

        foreach ($this->routines as $r)
            if ($r instanceof Through)
                $proxyResponse = $this->syncCall($method, $r, $params);

        if (is_callable($proxyResponse))
            $response = $proxyResponse($response);

        if (false === $proxyResponse)
            return $response;

        $this->reset();
        return $response;
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

    protected function syncCall($method, AbstractRoutine $routine, &$params)
    {
        $reflection = $this->getReflection($method);

        $cbParams = array();

        foreach ($routine->getParameters() as $p)
            $cbParams[] = $this->extractParam($reflection, $p, $params);

        return $routine->call($this, $cbParams);
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