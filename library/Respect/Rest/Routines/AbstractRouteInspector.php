<?php
namespace Respect\Rest\Routines;

use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Request;
use \SplObjectStorage;
use \UnexpectedValueException;
use \Closure;
use \Countable;
use \Iterator;
use \Traversable;
use \Serializable;
use \ArrayAccess;

abstract class AbstractRouteInspector implements ProxyableThrough,
    RouteInspector, Routinable, Unique, Countable, Iterator,
    Serializable, ArrayAccess
{
    public $routeInfo = null;

    abstract public function routeInfoResponse($data,
            AbstractRouteInspector $routeInfo, Request $request, $params);

    public function inspect(array $routes, AbstractRoute $active,
                                        $allowedMethods, $method, $uri)
    {
        if (empty($this->routeInfo))
                    $this->routeInfo = new SplObjectStorage();

        $route_keys = array();
        foreach ($routes as $route) {
            if (!isset($route_keys[$route->pattern])) {
                $route_keys[$route->pattern] = (object)$route->pattern;
                $this[$route_keys[$route->pattern]] = (object) array(
                  'active'          => false,
                  'uriTemplate'    => $this->createUriTemplate($route),
                  'methods'         => array(),
                  'routines'        => array(),
                );
            }
            $routeInfo = &$this[$route_keys[$route->pattern]];
            if ($route == $active) {
                $routeInfo->active = true;
                $routeInfo->uri = $uri;
                $routeInfo->method = $method;
            }
            $routeInfo->methods[] = $route->method;
            $routeInfo->routines += $this->parseRoutines($route->routines);
        }
    }

    public function getActiveRoute ()
    {
        $this->rewind();
        while ($this->valid()) {
            $route = $this[$this->current()];

            if ($route->active)
                return $route;

            $this->next();
        }
        return false;
    }

    public function parseRoutines($routines)
    {
        $data = array();
        foreach ($routines as $key => $value) {
            if (false !== strpos($key, __NAMESPACE__) // we will only considere Respect/Rest/Routines
                || false !== strpos(get_class($value), __NAMESPACE__))
                $data[$key] = array(
                    'key' => get_class($value),
                    'types' => method_exists($value, 'getKeys')
                                ? $value->getKeys()
                                : (property_exists($value, 'realm')
                                  && isset($value->realm)
                                    ?  array('realm' => $value->realm)
                                    : array('exported'=>  PHP_EOL .
                                                var_export($value, true))),
                );
        }
        return $data;
    }

    public function createUriTemplate(AbstractRoute $route)
    {
        $parameters = array();
        foreach ($route->getReflection(true)->getParameters() as $parameter)
            $parameters[] = '{'.$parameter->name.'}';
        $uriTemplate = $self = new DynamicClass(array(
            'template' => call_user_method_array('createUri',$route,$parameters)
                          ?: '/',
            'parameters'      => $parameters,
            'pattern'         => $route->pattern,
            'matchPattern'    => $route->regexForMatch,
            'regexForReplace' => $route->regexForReplace,

        ));
        $uriTemplate->parseUrl = function ($url) use ($self) {
            if (!preg_match($self->matchPattern, $url, $matches))
                throw new \UnexpectedValueException(
                    "UriTemplate: The url $url is not a match for parsing.");
            $return = array();
            if (($cnt = count($self->parameters)) == count($matches) - 1)
                for ($i = 0; $i < $cnt; $i++)
                    $return[
                        preg_replace('/}|{/', '', $self->parameters[$i])
                    ] = $matches[$i + 1];
            return $return;
        };

        return $uriTemplate;
    }

    public function through(Request $request, $params)
    {
        return call_user_func_array(function ($routeInfo, $request, $params) {
            return function($data)use($routeInfo, $request, $params) {
                return call_user_method_array('routeInfoResponse', $routeInfo,
                        array($data, $routeInfo, $request, $params));
            };
        }, array($this, $request, $params));
    }

    /**
     * Purely sugar, delegate interface implementations to $this->routeinfo.
    **/
    public function count()
    {   return $this->routeInfo->count();
    }
    public function current()
    {   return $this->routeInfo->current();
    }
    public function key()
    {   return $this->routeInfo->key();
    }
    public function next()
    {   return $this->routeInfo->next();
    }
    public function offsetExists($offset)
    {   return $this->routeInfo->offsetExists($offset);
    }
    public function offsetGet($offset)
    {   return $this->routeInfo->offsetGet($offset);
    }
    public function offsetSet($offset, $value)
    {   return $this->routeInfo->offsetSet($offset, $value);
    }
    public function offsetUnset($offset)
    {   return $this->routeInfo->offsetUnset($offset);
    }
    public function rewind()
    {   return $this->routeInfo->rewind();
    }
    public function serialize()
    {   return $this->routeInfo->serialize();
    }
    public function unserialize($serialized)
    {   return $this->routeInfo->unserialize($serialized);
    }
    public function valid()
    {   return $this->routeInfo->valid();
    }
}

/**
 * @codeCoverageIgnore Private class - would be so if we were able
 *
 * DynamicClass server only to allow closures to be methods on
 * dynamically created url objects. It is the hope that eventually
 * PHP will evolve to make this work around redundant.
 */
class DynamicClass {
    private $members;
    public function __construct(array $members)
    {   $this->members = $members;
    }
    public function __call($method, $args)
    {   return $this->invokeClosureAsMethod($this->$method, $args);
    }
    private function invokeClosureAsMethod(Closure $closure, $args)
    {
        $method = new \ReflectionFunction($closure);
        return $method->invokeArgs ($args);
    }
    public function __get($key)
    {   return $this->members[$key];
    }
    public function __set($key,$value)
    {   $this->members[$key] = $value;
    }
    public function __isset($key)
    {   return isset($this->members[$key]);
    }
    public function __unset($key)
    {   unset($this->members[$key]);
    }
}
