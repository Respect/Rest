<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest;

use Exception;
use ReflectionClass;
use InvalidArgumentException;
use Respect\Rest\Routes\AbstractRoute;

/**
 * A router that contains many instances of routes.
 *
 * @method \Respect\Rest\Routes\AbstractRoute get(\string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute post(\string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute put(\string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute delete(\string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute head(\string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute options(\string $path, $routeTarget)
 * @method \Respect\Rest\Routes\AbstractRoute any(\string $path, $routeTarget)
 */
class Router
{
    /**
     * @var bool true if this router dispatches itself when destroyed, false
     * otherwise
     */
    public $isAutoDispatched = true;

    /**
     * @var bool true if this router accepts _method HTTP hacks for PUT and
     * DELETE via POST
     */
    public $methodOverriding = false;

    /**
     * @var array An array of routines that must be applied to every route
     * instance
     */
    protected $globalRoutines = array();

    /**
     * @var array An array of main routes for this router
     */
    protected $routes = array();

    /**
     * @var array An array of side routes (errors, exceptions, etc) for this
     * router
     */
    protected $sideRoutes = array();

    /**
     * @var string The prefix for every requested URI starting with a slash
     */
    protected $virtualHost = '';

    /**
     * Compares two patterns and returns the first one according to
     * similarity or ocurrences of a subpattern
     *
     * @param string $patternA some pattern
     * @param string $patternB some pattern
     * @param string $sub      pattern needle
     *
     * @return bool true if $patternA is before $patternB
     */
    public static function compareOcurrences($patternA, $patternB, $sub)
    {
        return substr_count($patternA, $sub)
            < substr_count($patternB, $sub);
    }

    /**
     * Compares two patterns and returns the first one according to
     * similarity or presence of catch-all pattern
     *
     * @param string $patternA some pattern
     * @param string $patternB some pattern
     *
     * @return bool true if $patternA is before $patternB
     */
    public static function comparePatternSimilarity($patternA, $patternB)
    {
        return 0 === stripos($patternA, $patternB)
            || $patternA === AbstractRoute::CATCHALL_IDENTIFIER;
    }

    /**
     * Compares two patterns and returns the first one according to
     * similarity, patterns or ocurrences of a subpattern
     *
     * @param string $patternA some pattern
     * @param string $patternB some pattern
     * @param string $sub      pattern needle
     *
     * @return bool true if $patternA is before $patternB
     */
    public static function compareRoutePatterns($patternA, $patternB, $sub)
    {
        return static::comparePatternSimilarity($patternA, $patternB)
            || static::compareOcurrences($patternA, $patternB, $sub);
    }

    /**
     * Cleans up an return an array of extracted parameters
     *
     * @param array $params an array of params
     *
     * @see Respect\Rest\Request::$params
     *
     * @return array only the non-empty params
     */
    protected static function cleanUpParams(array $params)
    {
        //using array_values to reset array keys
        return array_values(
            array_filter(
                $params,
                function ($param) {

                    //remove any empty string param
                    return $param !== '';
                }
            )
        );
    }

    /**
     * Builds and appends many kinds of routes magically.
     *
     * @param string $method The HTTP method for the new route
     */
    public function __call($method, $args)
    {
        if (count($args) < 2) {
            throw new InvalidArgumentException(
                'Any route binding must at least 2 arguments'
            );
        }

        list($path, $routeTarget) = $args;

         // Support multiple route definitions as array of paths
        if (is_array($path)) {
            $lastPath = array_pop($path);
            foreach ($path as $p) {
                $this->$method($p, $routeTarget);
            }

            return $this->$method($lastPath, $routeTarget);
        }

        //closures, func names, callbacks
        if (is_callable($routeTarget)) {
            //raw callback
            if (!isset($args[2])) {
                return $this->callbackRoute($method, $path, $routeTarget);
            } else {
                return $this->callbackRoute(
                    $method,
                    $path,
                    $routeTarget,
                    $args[2]
                );
            }

        //direct instances
        } elseif ($routeTarget instanceof Routable) {
            return $this->instanceRoute($method, $path, $routeTarget);

        //static returns the argument itself
        } elseif (!is_string($routeTarget)) {
            return $this->staticRoute($method, $path, $routeTarget);

        //static returns the argument itself
        } elseif (
            is_string($routeTarget)
            && !(class_exists($routeTarget) || interface_exists($routeTarget))
        ) {
            return $this->staticRoute($method, $path, $routeTarget);

        //classes
        } else {
            //raw classnames
            if (!isset($args[2])) {
                return $this->classRoute($method, $path, $routeTarget);

             //classnames as factories
            } elseif (is_callable($args[2])) {
                return $this->factoryRoute(
                    $method,
                    $path,
                    $routeTarget,
                    $args[2]
                );

            //classnames with constructor arguments
            } else {
                return $this->classRoute(
                    $method,
                    $path,
                    $routeTarget,
                    $args[2]
                );
            }
        }
    }

    /**
     * @param mixed $virtualHost null for no virtual host or a string prefix
     *                           for every URI
     */
    public function __construct($virtualHost = null)
    {
        $this->virtualHost = $virtualHost;
    }

    /** If $this->autoDispatched, dispatches the app */
    public function __destruct()
    {
        if (!$this->isAutoDispatched || !isset($_SERVER['SERVER_PROTOCOL'])) {
            return;
        }

        echo $this->run();
    }

    /** Runs the router and returns its output */
    public function __toString()
    {
        $string = '';
        try {
            $string = (string) $this->run();
        } catch (\Exception $exception) {
            trigger_error($exception->getMessage(), E_USER_ERROR);
        }

        return $string;
    }

    /**
     * Applies a routine to every route
     *
     * @param string $routineName a name of some routine (Accept, When, etc)
     * @param array  $param1      some param
     * @param array  $param2      some param
     * @param array  $etc         This function accepts infinite params
     *                            that will be passed to the routine instance
     *
     * @see Respect\Rest\Request::$params
     *
     * @return Router the router itself.
     */
    public function always($routineName, $param1 = null, $param2 = null, $etc = null)
    {
        $params                 = func_get_args();
        $routineName            = array_shift($params);
        $routineClassName       = 'Respect\\Rest\\Routines\\'.$routineName;
        $routineClass           = new ReflectionClass($routineClassName);
        $routineInstance        = $routineClass->newInstanceArgs($params);
        $this->globalRoutines[] = $routineInstance;

        foreach ($this->routes as $route) {
            $route->appendRoutine($routineInstance);
        }

        return $this;
    }

    /**
     * Appends a pre-built route to the dispatcher
     *
     * @param AbstractRoute $route Any route
     *
     * @return Router the router itself
     */
    public function appendRoute(AbstractRoute $route)
    {
        $this->routes[]     = $route;
        $route->sideRoutes  = &$this->sideRoutes;
        $route->virtualHost = $this->virtualHost;

        foreach ($this->globalRoutines as $routine) {
            $route->appendRoutine($routine);
        }

        return $this;
    }

    /**
     * Appends a pre-built side route to the dispatcher
     *
     * @param AbstractRoute $route Any route
     *
     * @return Router the router itself
     */
    public function appendSideRoute(AbstractRoute $route)
    {
        $this->sideRoutes[] = $route;

        foreach ($this->globalRoutines as $routine) {
            $route->appendRoutine($routine);
        }

        return $this;
    }

    /**
     * Creates and returns a callback-based route
     *
     * @param string   $method    The HTTP method
     * @param string   $path      The URI pattern for this route
     * @param callable $callback  Any callback for this route
     * @param array    $arguments Additional arguments for the callback
     *
     * @return Respect\Rest\Routes\Callback The route instance
     */
    public function callbackRoute(
        $method,
        $path,
        $callback,
        array $arguments = array()
    ) {
        $route = new Routes\Callback($method, $path, $callback, $arguments);
        $this->appendRoute($route);

        return $route;
    }

    /**
     * Creates and returns a class-based route
     *
     * @param string $method    The HTTP method
     * @param string $path      The URI pattern for this route
     * @param string $class     Some class name
     * @param array  $arguments The class constructor arguments
     *
     * @return Respect\Rest\Routes\ClassName The route instance
     */
    public function classRoute($method, $path, $class, array $arguments = array())
    {
        $route = new Routes\ClassName($method, $path, $class, $arguments);
        $this->appendRoute($route);

        return $route;
    }

    /**
     * Dispatches the router
     *
     * @param mixed $method null to infer it or an HTTP method (GET, POST, etc)
     * @param mixed $uri    null to infer it or a request URI path (/foo/bar)
     *
     * @return mixed Whatever you returned from your model
     */
    public function dispatch($method = null, $uri = null)
    {
        return $this->dispatchRequest(new Request($method, $uri));
    }

    /**
     * Dispatch the current route with a custom Request
     *
     * @param Request $request Some request
     *
     * @return mixed Whatever the dispatched route returns
     */
    public function dispatchRequest(Request $request = null)
    {
        if ($this->isRoutelessDispatch($request)) {
            return $this->request;
        }

        return $this->routeDispatch();
    }

    /**
     * Creates and returns a side-route for catching exceptions
     *
     * @param string $className The name of the exception class you want to
     *                          catch. 'Exception' will catch them all.
     * @param string $callback  The function to run when an exception is cautght
     *
     * @return Respect\Rest\Routes\Exception
     */
    public function exceptionRoute($className, $callback = null)
    {
        $route = new Routes\Exception($className, $callback);
        $this->appendSideRoute($route);

        return $route;
    }

    /**
     * Creates and returns a side-route for catching errors
     *
     * @param string $callback The function to run when an error is cautght
     *
     * @return Respect\Rest\Routes\Error
     */
    public function errorRoute($callback)
    {
        $route = new Routes\Error($callback);
        $this->appendSideRoute($route);

        return $route;
    }

    /**
     * Creates and returns an factory-based route
     *
     * @param string $method    The HTTP metod (GET, POST, etc)
     * @param string $path      The URI Path (/foo/bar...)
     * @param string $className The class name of the factored instance
     * @param string $factory   Any callable
     *
     * @return Respect\Rest\Routes\Factory The route created
     */
    public function factoryRoute($method, $path, $className, $factory)
    {
        $route = new Routes\Factory($method, $path, $className, $factory);
        $this->appendRoute($route);

        return $route;
    }

    /**
     * Iterates over a list of routes and return the allowed methods for them
     *
     * @param array $routes an array of AbstractRoute
     *
     * @return array an array of unique allowed methods
     */
    public function getAllowedMethods(array $routes)
    {
        $allowedMethods = array();

        foreach ($routes as $route) {
            $allowedMethods[] = $route->method;
        }

        return array_unique($allowedMethods);
    }

    /**
     * Checks if router overrides the method with _method hack
     *
     * @return bool true if the router overrides current request method, false
     *              otherwise
     */
    public function hasDispatchedOverridenMethod()
    {
        return $this->request                    //Has dispatched
            && $this->methodOverriding           //Has method overriting
            && isset($_REQUEST['_method'])       //Has a hacky parameter
            && $this->request->method == 'POST'; //Only post is allowed for this
    }

    /**
     * Creates and returns an instance-based route
     *
     * @param string $method  The HTTP metod (GET, POST, etc)
     * @param string $path    The URI Path (/foo/bar...)
     * @param string $intance An instance of Routinable
     *
     * @return Respect\Rest\Routes\Instance The route created
     */
    public function instanceRoute($method, $path, $instance)
    {
        $route = new Routes\Instance($method, $path, $instance);
        $this->appendRoute($route);

        return $route;
    }

    /**
     * Checks if request is a global OPTIONS (OPTIONS * HTTP/1.1)
     *
     * @return bool true if the request is a global options, false otherwise
     */
    public function isDispatchedToGlobalOptionsMethod()
    {
        return $this->request->method === 'OPTIONS'
            && $this->request->uri === '*';
    }

    /**
     * Checks if a request doesn't apply for routes at all
     *
     * @param Request $request A request
     *
     * @return bool true if the request doesn't apply for routes
     */
    public function isRoutelessDispatch(Request $request = null)
    {
        $this->isAutoDispatched = false;

        if (!$request) {
            $request = new Request();
        }

        $this->request = $request;

        if ($this->hasDispatchedOverridenMethod()) {
            $request->method = strtoupper($_REQUEST['_method']);
        }

        if ($this->isDispatchedToGlobalOptionsMethod()) {
            $allowedMethods = $this->getAllowedMethods($this->routes);

            if ($allowedMethods) {
                header('Allow: '.implode(', ', array_unique($allowedMethods)));
            }

            return true;
        }
    }

    /**
     * Performs the main route dispatching mechanism
     */
    public function routeDispatch()
    {
        $this->applyVirtualHost();
        $this->sortRoutesByComplexity();

        $matchedByPath  = $this->getMatchedRoutesByPath();
        $allowedMethods = $this->getAllowedMethods(
            iterator_to_array($matchedByPath)
        );

        //OPTIONS? Let's inform the allowd methods
        if ($this->request->method === 'OPTIONS' && $allowedMethods) {
            header('Allow: '.implode(', ', $allowedMethods));
        } elseif (0 === count($matchedByPath)) {
            header('HTTP/1.1 404');
        } elseif (!$this->routineMatch($matchedByPath) instanceof Request) {
            $this->informMethodNotAllowed($allowedMethods);
        }

        return $this->request;
    }

    /**
     * Dispatches and get response with default request parameters
     *
     * @param Request $request Some request
     *
     * @return string the response string
     */
    public function run(Request $request = null)
    {
        $route = $this->dispatchRequest($request);
        if (
            !$route
            || (isset($request->method)
                && $request->method === 'HEAD')
        ) {
            return;
        }

        $response = $route->response();

        if (is_resource($response)) {
            fpassthru($response);

            return '';
        }

        return (string) $response;
    }

    /**
     * Creates and returns a static route
     *
     * @param string $method      The HTTP metod (GET, POST, etc)
     * @param string $path        The URI Path (/foo/bar...)
     * @param string $staticValue Some static value to be printed
     *
     * @return Respect\Rest\Routes\StaticValue The route created
     */
    public function staticRoute($method, $path, $staticValue)
    {
        $route = new Routes\StaticValue($method, $path, $staticValue);
        $this->appendRoute($route);

        return $route;
    }

    /** Appliesthe virtualHost prefix on the current request */
    protected function applyVirtualHost()
    {
        if ($this->virtualHost) {
            $this->request->uri = preg_replace(
                '#^'.preg_quote($this->virtualHost).'#',
                '',
                $this->request->uri
            );
        }
    }

    /**
     * Configures a request for a specific route with specific parameters
     *
     * @param Request       $request Some request
     * @param AbstractRoute $route   Some route
     * @param array         $param   A list of URI params
     *
     * @see Respect\Rest\Request::$params
     *
     * @return Request a configured Request instance
     */
    protected function configureRequest(
        Request $request,
        AbstractRoute $route,
        array $params = array()
    ) {
        $request->route = $route;
        $request->params = $params;

        return $request;
    }

    /**
     * Return routes matched by path
     *
     * @return SplObjectStorage a list of routes matched by path
     */
    protected function getMatchedRoutesByPath()
    {
        $matched = new \SplObjectStorage();

        foreach ($this->routes as $route) {
            if ($this->matchRoute($this->request, $route, $params)) {
                $matched[$route] = $params;
            }
        }

        return $matched;
    }

    /**
     * Sends an Allow header with allowed methods from a list
     *
     * @param array $allowedMehods A list of allowed methods
     *
     * @return null sends an Allow header.
     */
    protected function informAllowedMethods(array $allowedMethods)
    {
        header('Allow: '.implode(', ', $allowedMethods));
    }

    /**
     * Informs the PHP environment of a not allowed method alongside
     * its allowed methods for that path
     *
     * @param array $allowedMehods A list of allowed methods
     *
     * @return null sends HTTP Status Line and Allow header.
     */
    protected function informMethodNotAllowed(array $allowedMethods)
    {
        header('HTTP/1.1 405');

        if (!$allowedMethods) {
            return;
        }

        $this->informAllowedMethods($allowedMethods);
        $this->request->route = null;
    }

    /**
     * Checks if a route matches a method
     *
     * @param AbstractRoute $route      A route instance
     * @param string        $methodName Name of the method to match
     *
     * @return bool true if route matches
     */
    protected function matchesMethod(AbstractRoute $route, $methodName)
    {
        return 0 !== stripos($methodName, '__')
            && ($route->method === $this->request->method
                || $route->method === 'ANY'
                || ($route->method === 'GET'
                    && $this->request->method === 'HEAD'
                )
            );
    }

    /**
     * Returns true if the passed route matches the passed request
     *
     * @param Request       $request Some request
     * @param AbstractRoute $route   Some route
     * @param array         $params  A list of URI params
     *
     * @see Respect\Rest\Request::$params
     *
     * @return bool true if the route matches the request with that params
     */
    protected function matchRoute(
        Request $request,
        AbstractRoute $route,
        &$params = array()
    ) {
        if ($route->match($request, $params)) {
            $request->route = $route;

            return true;
        }
    }

    /**
     * Checks if a route matches its routines
     *
     * @param SplObjectStorage $matchedByPath A list of routes matched by path
     *
     * @return bool true if route matches its routines
     */
    protected function routineMatch(\SplObjectStorage $matchedByPath)
    {
        $badRequest = false;

        foreach ($matchedByPath as $route) {
            if ($this->matchesMethod($route, $this->request->method)) {
                $tempParams = $matchedByPath[$route];
                if ($route->matchRoutines($this->request, $tempParams)) {
                    return $this->configureRequest(
                        $this->request,
                        $route,
                        static::cleanUpParams($tempParams)
                    );
                } else {
                    $badRequest = true;
                }
            }
        }

        return $badRequest ? false : null;
    }

    /** Sorts current routes according to path and parameters */
    protected function sortRoutesByComplexity()
    {
        usort(
            $this->routes,
            function ($a, $b) {
                $a = $a->pattern;
                $b = $b->pattern;
                $pi = AbstractRoute::PARAM_IDENTIFIER;

                //Compare similarity and ocurrences of "/"
                if (Router::compareRoutePatterns($a, $b, '/')) {
                    return 1;

                //Compare similarity and ocurrences of /*
                } elseif (Router::compareRoutePatterns($a, $b, $pi)) {
                    return -1;

                //Hard fallback for consistency
                } else {
                    return 1;
                }
            }
        );
    }
}
