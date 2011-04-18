<?php

namespace Respect\Rest;

use ReflectionClass;
use ReflectionMethod;
use InvalidArgumentException;

class Router
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

    protected $mode = 2;
    protected $autoDispatched = true;
    protected $routes = array();
    protected $controllerInstances = array();

    public static function cleanUpParams($params)
    {
        return array_filter(
            array_slice($params, 1), function($param) {
                return $param !== '';
            }
        );
    }

    public function __call($method, $arguments)
    {
        if (count($arguments) !== 2)
            throw new InvalidArgumentException('Any route binding must have exactly 2 arguments: a path and a callback');

        list($path, $callback) = $arguments;
        return $this->addRoute($method, $path, $callback);
    }

    public function __destruct()
    {
        if ($this->autoDispatched && isset($_SERVER['SERVER_PROTOCOL']))
            echo $this->dispatch();
    }

    public function addController($path, $class)
    {
        $reflection = new ReflectionClass($class);

        if (!$reflection->implementsInterface('\\Respect\\Rest\\Routable'))
            throw new InvalidArgumentException('Binded controllers must implement the \\Respect\\Rest\\Routable interface');

        $publicMethods = $reflection->getMethods(
                ReflectionMethod::IS_PUBLIC ^ ~ ReflectionMethod::IS_STATIC
        );
        if (is_object($class))
            $this->controllerInstances[$reflection->getName()] = $class;

        foreach ($publicMethods as $method)
            if (false === stripos($method->getName(), '__'))
                $this->addRoute(
                    $method->getName(),
                    $path,
                    (is_object($class) ?
                        array($class, $method->getName()) :
                        $this->createLoader(
                            $reflection,
                            $method,
                            (func_num_args() > 2 ?
                                array_slice(func_get_args(), 2) :
                                array()
                            )
                        )
                    )
                );
    }

    public function addRoute($method, $path, $callback)
    {
        if (!is_callable($callback))
            throw new InvalidArgumentException('Route callback must be callable');

        $patterns = $this->createRegexPatterns($path);
        $route = new Route(
                strtoupper($method), $callback, $patterns[0], $patterns[1]
        );
        $this->appendRoute($route);
        return $route;
    }

    public function appendRoute(Route $route)
    {
        $method = $route->getMethod();
        $this->routes[$method][] = $route;
        uksort($this->routes[$method], function($a, $b) {
                return substr_count($a, Router::REGEX_SINGLE_PARAM)
                < substr_count($b, Router::REGEX_SINGLE_PARAM);
            }
        );
    }

    public function dispatch($method=null, $uri=null)
    {
        $this->autoDispatched = false;
        $method = strtoupper($method ? : $_SERVER['REQUEST_METHOD']);
        $uri = $uri ? : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = rtrim($uri, ' /');

        if (!isset($this->routes[$method]))
            return;

        foreach ($this->routes[$method] as $route)
            if ($route->match($uri, $params))
                return call_user_func_array(
                    $route, static::cleanUpParams($params)
                );
    }

    public function setAutoDispatched($autoDispatched)
    {
        $this->autoDispatched = $autoDispatched;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
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
                    ),
                    $pathQuoted
            );

        return $pathQuoted;
    }

    protected function createLoader(ReflectionClass $class, ReflectionMethod $method, $args)
    {
        $instances = &$this->controllerInstances;
        $routes = &$this->routes;

        return function() use(&$routes, &$instances, $class, $method, $args) {
            $className = $class->getName();

            if (!isset($instances[$className]))
                if (method_exists($className, '__construct'))
                    $instances[$className] = $class->newInstanceArgs($args);
                else
                    $instances[$className] = new $className;

            $methodCall = array($instances[$className], $method->getName());
            return call_user_func_array($methodCall, func_get_args());
        };
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