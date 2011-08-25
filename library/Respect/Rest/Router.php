<?php

namespace Respect\Rest;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use InvalidArgumentException;
use Respect\Rest\Routes;
use Respect\Rest\Routes\AbstractRoute;

class Router
{

    public $isAutoDispatched = true;
    protected $globalRoutines = array();
    protected $routes = array();
    protected $virtualHost = '';

    /** Cleans up an return an array of extracted parameters */
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
        if (count($arguments) < 2)
            throw new InvalidArgumentException('Any route binding must at least 2 arguments');

        list ($path, $routeTarget) = $arguments;

        if (is_callable($routeTarget))
            return $this->callbackRoute($method, $path, $routeTarget);
        elseif (is_object($routeTarget))
            return $this->instanceRoute($method, $path, $routeTarget);
        elseif (is_string($routeTarget)) {
            array_unshift($arguments, $method);
            return call_user_func_array(array($this, 'classRoute'), $arguments);
        }
    }

    public function __construct($virtualHost=null)
    {
        $this->virtualHost = $virtualHost;
    }

    public function __destruct()
    {
        if (!$this->isAutoDispatched || !isset($_SERVER['SERVER_PROTOCOL']))
            return;

        echo $this->run();
    }

    /** Applies a routine to every route */
    public function always($routineName, $routineParameter)
    {
        $routineClass = 'Respect\\Rest\\Routines\\' . $routineName;
        $routineInstance = new $routineClass($routineParameter);
        $this->globalRoutines[] = $routineInstance;

        foreach ($this->routes as $route)
            $route->appendRoutine($routineInstance);

        return $this;
    }

    /** Appends a pre-built route to the dispatcher */
    public function appendRoute(AbstractRoute $route)
    {
        $this->routes[] = $route;

        foreach ($this->globalRoutines as $routine)
            $route->appendRoutine($routine);
    }

    /** Creates and returns a callback-based route */
    public function callbackRoute($method, $path, $callback)
    {
        $route = new Routes\Callback($method, $path, $callback);
        $this->appendRoute($route);
        return $route;
    }

    /** Creates and returns a class-based route */
    public function classRoute($method, $path, $class, $arg1=null, $etc=null)
    {
        $args = func_num_args() > 3 ? array_slice(func_get_args(), 3) : array();
        $route = new Routes\ClassName($method, $path, $class, $args);
        $this->appendRoute($route);
        return $route;
    }

    /** Dispatch the current route with a standard Request */
    public function dispatch($method=null, $uri=null)
    {
        return $this->dispatchRequest(new Request($method, $uri));
    }

    /** Dispatch the current route with a custom Request */
    public function dispatchRequest(Request $request=null)
    {
        usort($this->routes, function($a, $b) {
                $a = $a->pattern;
                $b = $b->pattern;

                if (0 === stripos($a, $b) || $a == AbstractRoute::CATCHALL_IDENTIFIER)
                    return 1;
                elseif (0 === stripos($b, $a) || $b == AbstractRoute::CATCHALL_IDENTIFIER)
                    return -1;
                elseif (substr_count($a, '/') < substr_count($b, '/'))
                    return 1;

                return substr_count($a, AbstractRoute::PARAM_IDENTIFIER)
                    < substr_count($b, AbstractRoute::PARAM_IDENTIFIER) ? -1 : 1;
            }
        );
        $this->isAutoDispatched = false;
        if (!$request)
            $request = new Request;

        if ($this->virtualHost)
            $request->uri =
                preg_replace('#^' . preg_quote($this->virtualHost) . '#', '', $request->uri);

        foreach ($this->routes as $route)
            if ($this->matchRoute($request, $route, $params))
                return $this->configureRequest($request, $route, static::cleanUpParams($params));

        $request->route = null;
        return $request;
    }

    /** Dispatches and get response with default request parameters */
    public function run()
    {
        $route = $this->dispatch();
        return $route ? $route->response() : null;
    }

    /** Creates and returns an instance-based route */
    public function instanceRoute($method, $path, $instance)
    {
        $route = new Routes\Instance($method, $path, $instance);
        $this->appendRoute($route);
        return $route;
    }

    /** Configures a request for a specific route with specific parameters */
    protected function configureRequest(Request $request, AbstractRoute $route, array $params)
    {
        $request->route = $route;
        $request->params = $params;
        return $request;
    }

    /** Returns true if the passed route matches the passed request */
    protected function matchRoute(Request $request, AbstractRoute $route, &$params=array())
    {
        $request->route = $route;
        return $route->match($request, $params);
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
