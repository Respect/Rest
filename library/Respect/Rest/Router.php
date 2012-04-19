<?php

namespace Respect\Rest;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use InvalidArgumentException;
use Respect\Rest\Routes;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Exception\MethodNotAllowed;

class Router
{

    public $isAutoDispatched = true;
    public $methodOverriding = false;
    protected $globalRoutines = array();
    protected $routes = array();
    protected $virtualHost = '';

    /** Cleans up an return an array of extracted parameters */
    public static function cleanUpParams($params)
    {
        return array_values(
            array_filter(
                $params, function($param) {
                   return $param !== '';
                }
            )
        );
    }

    public function __call($method, $args)
    {
        if (count($args) < 2)
            throw new InvalidArgumentException('Any route binding must at least 2 arguments');

        list ($path, $routeTarget) = $args;

        if (is_callable($routeTarget)) //closures, func names, callbacks
            return $this->callbackRoute($method, $path, $routeTarget);
        elseif ($routeTarget instanceof Routable) //direct instances
            return $this->instanceRoute($method, $path, $routeTarget);
        elseif (!is_string($routeTarget)) //static returns the argument itself
            return $this->staticRoute($method, $path, $routeTarget);
        elseif (is_string($routeTarget) && !class_exists($routeTarget))
            return $this->staticRoute($method, $path, $routeTarget);
        else
            if (!isset($args[2])) //raw classnames
                return $this->classRoute($method, $path, $routeTarget);
            elseif (is_callable($args[2])) //classnames as factories
                return $this->factoryRoute($method, $path, $routeTarget, $args[2]);
            else //classnames with constructor arguments
                return $this->classRoute($method, $path, $routeTarget, $args[2]);
    }

    public function __construct($virtualHost=null)
    {
        $this->virtualHost = $virtualHost;
    }

    public function __destruct()
    {
        if (!$this->isAutoDispatched || !isset($_SERVER['SERVER_PROTOCOL']))
            return;

        echo $this;
    }
    
    public function __toString()
    {
        return $this->run();
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
    public function classRoute($method, $path, $class, array $arguments=array())
    {
        $route = new Routes\ClassName($method, $path, $class, $arguments);
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
        $this->isAutoDispatched = false;
        if (!$request)
            $request = new Request;

        if ($this->methodOverriding && isset($_REQUEST['_method']))
            $request->method = strtoupper($_REQUEST['_method']);

        if ($request->method === 'OPTIONS' && $request->uri === '*') {
            $allowedMethods = array();

            foreach ($this->routes as $route) 
                $allowedMethods[] = $route->method;

            if ($allowedMethods)
                header('Allow: '.implode(', ', $allowedMethods));

            return $request;
        }

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

        if ($this->virtualHost)
            $request->uri =
                preg_replace('#^' . preg_quote($this->virtualHost) . '#', '', $request->uri);

        $matchedByPath = array();
        $allowedMethods = array();

        foreach ($this->routes as $route) 
            if ($this->matchRoute($request, $route, $tempParams)) {
                
                if (!isset($params)) 
                    $params = $tempParams;
                
                $matchedByPath[] = $route;
                $allowedMethods[] = $route->method;
            }

        if ($request->method === 'OPTIONS' && $allowedMethods) {
            header('Allow: '.implode(', ', $allowedMethods));
            return $request;
        }

        if (!$matchedByPath)
            header('HTTP/1.1 404');

        foreach ($matchedByPath as $route) 
            if (0 !== stripos($request->method, '__')
                && ($route->method === $request->method || $route->method === 'ANY' || ($route->method === 'GET' && $request->method === 'HEAD')))
                if ($route->matchRoutines($request, $params))
                    return $this->configureRequest($request, $route, static::cleanUpParams($params));
                else
                    $badRequest = true;

        if ($matchedByPath && !isset($badRequest))
            header('HTTP/1.1 405');

        if ($matchedByPath && $allowedMethods)
            header('Allow: '.implode(', ', $allowedMethods));

        $request->route = null;
        return $request;
    }

    /** Dispatches and get response with default request parameters */
    public function run(Request $request=null)
    {
        $route = $this->dispatchRequest($request);
        if (!$route || (isset($request->method) && $request->method === 'HEAD'))
            return null;

        $response = $route->response();
        if (is_resource($response)) {
            fpassthru($response);
            return '';
        }
        return (string) $response;

    }

    /** Creates and returns an factory-based route */
    public function factoryRoute($method, $path, $className, $factory)
    {
        $route = new Routes\Factory($method, $path, $className, $factory);
        $this->appendRoute($route);
        return $route;
    }

    /** Creates and returns an instance-based route */
    public function instanceRoute($method, $path, $instance)
    {
        $route = new Routes\Instance($method, $path, $instance);
        $this->appendRoute($route);
        return $route;
    }
    
    /** Creates and returns a static route */
    public function staticRoute($method, $path, $instance)
    {
        $route = new Routes\StaticValue($method, $path, $instance);
        $this->appendRoute($route);
        return $route;
    }

    /** Configures a request for a specific route with specific parameters */
    protected function configureRequest(Request $request, AbstractRoute $route, array $params=array())
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
