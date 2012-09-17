<?php

namespace Respect\Rest;

use Exception;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;
use InvalidArgumentException;
use Respect\Rest\Routes;
use Respect\Rest\Routes\AbstractRoute;

/**
 * @method \Respect\Rest\Routes\AbstractRoute get(string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute post(string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute put(string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute head(string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute delete(string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute any(string $path, $routeTarget)
 */
class Router
{

    public $isAutoDispatched = true;
    public $methodOverriding = false;
    protected $globalRoutines = array();
    protected $routes = array();
    protected $sideRoutes = array();
    protected $virtualHost = '';

    /** Cleans up an return an array of extracted parameters */
    protected static function cleanUpParams($params)
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
            if (!isset($args[2])) //raw callback
                return $this->callbackRoute($method, $path, $routeTarget);
            else
                return $this->callbackRoute($method, $path, $routeTarget, $args[2]);
        elseif ($routeTarget instanceof Routable) //direct instances
            return $this->instanceRoute($method, $path, $routeTarget);
        elseif (!is_string($routeTarget)) //static returns the argument itself
            return $this->staticRoute($method, $path, $routeTarget);
        elseif (is_string($routeTarget)
                && !(class_exists($routeTarget) || interface_exists($routeTarget)))
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
    public function always($routineName, $routineParameters)
    {
        $routineParameters = func_get_args();
        $routineName = array_shift($routineParameters);
        $routineClass = new ReflectionClass('Respect\\Rest\\Routines\\' . $routineName);
        $routineInstance = $routineClass->newInstanceArgs($routineParameters);
        $this->globalRoutines[] = $routineInstance;

        foreach ($this->routes as $route)
            $route->appendRoutine($routineInstance);

        return $this;
    }

    /** Appends a pre-built route to the dispatcher */
    public function appendRoute(AbstractRoute $route)
    {
        $this->routes[] = $route;
        $route->sideRoutes = &$this->sideRoutes;

        foreach ($this->globalRoutines as $routine)
            $route->appendRoutine($routine);
    }

    /** Appends a pre-built side route to the dispatcher */
    public function appendSideRoute(AbstractRoute $route)
    {
        $this->sideRoutes[] = $route;

        foreach ($this->globalRoutines as $routine)
            $route->appendRoutine($routine);
    }

    /** Creates and returns a callback-based route */
    public function callbackRoute($method, $path, $callback, array $arguments = array())
    {
        $route = new Routes\Callback($method, $path, $callback, $arguments);
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

    public function exceptionRoute($className, $path=null)
    {
        $route = new Routes\Exception($className, $path);
        $this->appendSideRoute($route);
        return $route;
    }

    public function errorRoute($callback)
    {
        $route = new Routes\Error($callback);
        $this->appendSideRoute($route);
        return $route;
    }

    public function hasDispatchedOverridenMethod() 
    {
        return $this->request                    //Has dispatched 
            && $this->methodOverriding           //Has method overriting
            && isset($_REQUEST['_method'])       //Has a parameter that triggers it
            && $this->request->method == 'POST'; //Only post is allowed for this
    }

    public function isDispatchedToGlobalOptionsMethod()
    {
        return $this->request->method === 'OPTIONS' && $this->request->uri === '*';
    }

    public function getAllowedMethods(array $routes)
    {        
        $allowedMethods = array();

        foreach ($routes as $route)
            $allowedMethods[] = $route->method;

        return $allowedMethods;
    }

    protected function sortRoutes()
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
    }

    protected function applyVirtualHost()
    {
        if ($this->virtualHost)
            $this->request->uri =
                preg_replace('#^' . preg_quote($this->virtualHost) . '#', '', $this->request->uri);
    }

    protected function getMatchedRoutesByPath()
    {
        $matched = new \SplObjectStorage;
        foreach ($this->routes as $route) {
            if ($this->matchRoute($this->request, $route, $params)) {
                $matched[$route] = $params;
            }
        }
        return $matched;
    }

    protected function getMatchedRoutesByRoutines(\SplObjectStorage $matchedByPath)
    {
        $badRequest = false;
        foreach ($matchedByPath as $route)
            if (0 !== stripos($this->request->method, '__')
                && ($route->method === $this->request->method
                    || $route->method === 'ANY'
                    || ($route->method === 'GET' && $this->request->method === 'HEAD')))
                if ($route->matchRoutines($this->request, $tempParams = $matchedByPath[$route]))
                    return $this->configureRequest($this->request, $route, static::cleanUpParams($tempParams));
                else
                    $badRequest = true;

        return $badRequest ? false : null;
    }

    public function isRoutelessDispatch(Request $request = null)
    {
        $this->isAutoDispatched = false;
        if (!$request)
            $request = new Request;
            
        $this->request = $request;

        if ($this->hasDispatchedOverridenMethod())
            $request->method = strtoupper($_REQUEST['_method']);

        if ($this->isDispatchedToGlobalOptionsMethod()) {
            $allowedMethods = $this->getAllowedMethods($this->routes);

            if ($allowedMethods)
                header('Allow: '.implode(', ', array_unique($allowedMethods)));

            return true;
        }
    }

    protected function informMethodNotAllowed(array $allowedMethods)
    {
        header('HTTP/1.1 405');

        if (!$allowedMethods) {
            return;
        }
        
        header('Allow: '.implode(', ', $allowedMethods));
        $this->request->route = null;
    }

    public function routeDispatch()
    {
        $this->applyVirtualHost();
        $this->sortRoutes();
        $matchedByPath = $this->getMatchedRoutesByPath();
        $allowedMethods = $this->getAllowedMethods(iterator_to_array($matchedByPath));

        if ($this->request->method === 'OPTIONS' && $allowedMethods) 
            header('Allow: '.implode(', ', $allowedMethods));
        elseif (0 === count($matchedByPath))
            header('HTTP/1.1 404');
        elseif (!$this->getMatchedRoutesByRoutines($matchedByPath) instanceof Request)
            $this->informMethodNotAllowed($allowedMethods);
        
        return $this->request;
    }

    /** Dispatch the current route with a custom Request */
    public function dispatchRequest(Request $request=null)
    {
        if ($this->isRoutelessDispatch($request)) 
            return $this->request;

        return $this->routeDispatch();
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
        if ($route->match($request, $params)) {
            $request->route = $route;
            return true;
        }
    }

}
