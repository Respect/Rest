<?php

namespace Respect\Rest\Routes;

use Respect\Rest\AnnotationParser;

use ReflectionClass;
use Respect\Rest\Request;
use \Respect\Rest\Routines\AbstractRoutine;
use \Respect\Rest\Routines\ProxyableWhen;

/** Base class for all Routes */
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

    public $method = '';
    public $pattern = '';
    public $regexForMatch = '';
    public $regexForReplace = '';
    public $routines = array();
    protected $dependencies;
    protected $containers;

    /** Returns the RelfectionFunctionAbstract object for the passed method */
    abstract public function getReflection($method);

    /** Runs the target method/params into this route */
    abstract public function runTarget($method, &$params);

    public function __construct($method, $pattern)
    {
        $this->pattern = $pattern;
        $this->method = strtoupper($method);
        list($this->regexForMatch, $this->regexForReplace)
            = $this->createRegexPatterns($pattern);
    }

    protected function hasDependencies($class)
    {
        $aParser = new AnnotationParser(new ReflectionClass($class));
        $this->dependencies = $aParser->parseDependencies();

        if (count($this->dependencies) > 0)
            return true;
            
        return false;
    }
    
    protected function injectDependencies($class)
    {
        if (! $this->hasDependencies($class))
            return;
        
        $found = false;
        foreach ($this->dependencies as $dependency) {
            foreach ($this->containers as $container) {
                if (isset($container->$dependency['dependency'])) {
                    $this->setProperty($class, $dependency['property'], $container->$dependency['dependency']);
                    $found = true;
                    break;
                }
            }
            
            if (!$found)
                throw new \RuntimeException("Dependency {$dependency['dependency']} could not be found in none of the containers");
                
            $found = false;
        }
    }
    
    protected function setProperty($class, $property, $value)
    {
        $rProp = new \ReflectionProperty($class, $property);
        
        if (!$rProp->isPublic())
            $rProp->setAccessible(true);
            
        $rProp->setValue($class, $value);
    }
    
    public function setContainers(array $containers)
    {
        $this->containers = $containers;
    }
    
    public function __call($method, $arguments)
    {
        $routineReflection = new ReflectionClass(
                'Respect\\Rest\\Routines\\' . ucfirst($method)
        );

        $this->appendRoutine($routineReflection->newInstanceArgs($arguments));

        return $this;
    }

    /** Appends a pre-built routine to this route */
    public function appendRoutine(AbstractRoutine $routine)
    {
        $this->routines[] = $routine;
    }

    /** Creates an URI for this route with the passed parameters */
    public function createUri($param1=null, $etc=null)
    {
        $params = func_get_args();
        array_unshift($params, $this->regexForReplace);
        return call_user_func_array(
                'sprintf', $params
        );
    }

    /** Checks if this route matches a request */
    public function match(Request $request, &$params=array())
    {
        if (($request->method !== $this->method && $this->method !== 'ANY')
            || 0 === stripos($request->method, '__'))
            return false;

        foreach ($this->routines as $routine)
            if ($routine instanceof ProxyableWhen
                && !$request->routineCall('when', $request->method, $routine, $params))
                return false;

        $matchUri = preg_replace('#(\.\w+)*$#', '', $request->uri);

        if (!preg_match($this->regexForMatch, $matchUri, $params))
            return false;

        if (count($params) > 1 && false !== stripos(end($params), '/')) {
            $lastParam = array_pop($params);
            $params = array_merge($params, explode('/', $lastParam));
        }

        return true;
    }

    /** Creates the regex from the route patterns */
    protected function createRegexPatterns($pattern)
    {
        $pattern = rtrim($pattern, ' /');
        $extra = $this->extractCatchAllPattern($pattern);
        $matchPattern = str_replace(
            static::QUOTED_PARAM_IDENTIFIER, static::REGEX_SINGLE_PARAM, preg_quote($pattern), $paramCount
        );
        $replacePattern = str_replace(static::PARAM_IDENTIFIER, '/%s', $pattern);
        $matchPattern = $this->fixOptionalParams($matchPattern);
        $matchRegex = "#^{$matchPattern}{$extra}$#";
        return array($matchRegex, $replacePattern);
    }

    /** Extracts the catch-all param from a pattern */
    protected function extractCatchAllPattern(&$pattern)
    {
        $extra = static::REGEX_CATCHALL;

        if ((strlen($pattern) - strlen(static::CATCHALL_IDENTIFIER))
            === strripos($pattern, static::CATCHALL_IDENTIFIER))
            $pattern = substr($pattern, 0, -3);
        else
            $extra = '';

        $pattern = str_replace(
            static::CATCHALL_IDENTIFIER, static::PARAM_IDENTIFIER, $pattern
        );
        return $extra;
    }

    /** Turn sequenced parameters optional */
    protected function fixOptionalParams($quotedPattern)
    {
        if (strlen($quotedPattern) - strlen(static::REGEX_TWO_ENDING_PARAMS)
            === strripos($quotedPattern, static::REGEX_TWO_ENDING_PARAMS))
            $quotedPattern = str_replace(
                array(
                static::REGEX_TWO_ENDING_PARAMS,
                static::REGEX_TWO_MIXED_PARAMS
                ), array(
                static::REGEX_TWO_OPTIONAL_ENDING_PARAMS,
                static::REGEX_TWO_OPTIONAL_PARAMS
                ), $quotedPattern
            );

        return $quotedPattern;
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