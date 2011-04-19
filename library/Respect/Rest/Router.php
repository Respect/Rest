<?php

namespace Respect\Rest;

use ReflectionClass;
use ReflectionMethod;
use InvalidArgumentException;
use Respect\Rest\Routes;

class Router
{

    protected $mode = 2;
    protected $autoDispatched = true;
    protected $routes = array();

    public static function cleanUpParams($params)
    {
        return array_filter(
            array_slice($params, 1),
            function($param) {
                return $param !== '';
            }
        );
    }

    public function __call($method, $arguments)
    {
        if (count($arguments) !== 2)
            throw new InvalidArgumentException('Any route binding must have exactly 2 arguments: a path and a callback');

        list($path, $callback) = $arguments;
        return $this->callbackRoute($method, $path, $callback);
    }

    public function __destruct()
    {
        if ($this->autoDispatched && isset($_SERVER['SERVER_PROTOCOL']))
            echo $this->dispatch();
    }

    public function callbackRoute($method, $path, $callback)
    {
        $route = new Routes\Callback($method, $path);
        $route->setCallback($callback);
        $this->append($route);
        return $route;
    }

    public function classRoute($method, $path, $class, $arg1=null, $etc=null)
    {
        $args = func_num_args() > 3 ? array_slice(func_get_args(), 3) : array();
        $route = new Routes\ClassName($method, $path);
        $route->setClass($class);
        call_user_func_array(array($route, 'setArguments'), $args);
        $this->append($route);
        return $route;
    }

    public function instanceRoute($method, $path, $instance)
    {
        $route = new Routes\Instance($method, $path);
        $route->setInstance($instance);
        $this->append($route);
        return $route;
    }

    public function lazyRoute($method, $path, $loader)
    {
        $route = new Routes\Lazy($method, $path);
        $route->setLoader($loader);
        $this->append($route);
        return $route;
    }

    public function append(Routes\AbstractRoute $route)
    {
        $this->routes[] = $route;
        usort($this->routes,
            function($a, $b) {
                return substr_count($a->getMatchPattern(),
                    Routes\AbstractRoute::REGEX_SINGLE_PARAM)
                < substr_count($b->getMatchPattern(),
                    Routes\AbstractRoute::REGEX_SINGLE_PARAM);
            }
        );
    }

    public function dispatch($method=null, $uri=null)
    {
        $this->autoDispatched = false;
        $method = strtoupper($method ? : $_SERVER['REQUEST_METHOD']);
        $uri = $uri ? : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = rtrim($uri, ' /');

        foreach ($this->routes as $route)
            if ($route->match($uri, $method, $params))
                return $route->run($method, static::cleanUpParams($params));
    }

    public function setAutoDispatched($autoDispatched)
    {
        $this->autoDispatched = $autoDispatched;
    }

    public function setMode($mode)
    {
        $this->mode = $mode;
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
