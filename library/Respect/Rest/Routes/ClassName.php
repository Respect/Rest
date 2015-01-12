<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionMethod;
use Respect\Rest\Routable;

/** A route that builds an instance of a class to run it */
class ClassName extends AbstractRoute
{
    /** @var string The class name this route will instantiate */
    public $class = '';

    /** @var array Constructor params for the built instance */
    public $constructorParams = array();

    /** @var object The built class instance */
    protected $instance = null;

    /**
     * @param string $method  The HTTP method (GET, POST, etc)
     * @param string $pattern The URI pattern for this route (like /users/*)
     * @param string $class   The class name
     * @param array  $params  Constructor params for the class
     *
     * @see Respect\Rest\Routes\ClassName::$class
     * @see Respect\Rest\Routes\ClassName::$constructorParams
     */
    public function __construct(
        $method,
        $pattern,
        $class,
        array $params = array()
    ) {
        $this->class = $class;
        $this->constructorParams = $params;
        parent::__construct($method, $pattern);
    }

    /**
     * Creates an instance of the class this route builds
     *
     * @return object The created instance
     */
    protected function createInstance()
    {
        $className = $this->class;
        $reflection = new ReflectionClass($className);

        if (!$reflection->implementsInterface('Respect\\Rest\\Routable')) {
            throw new InvalidArgumentException(
                'Routed classes must implement Respect\\Rest\\Routable'
            );
        }

        if (
            empty($this->constructorParams)
            || !method_exists($this->class, '__construct')
        ) {
            return new $className();
        }

        $reflection = new ReflectionClass($this->class);

        return $reflection->newInstanceArgs($this->constructorParams);
    }

    /**
     * Gets the reflection for a specific method. For this route, the reflection
     * is given for the class method having the same name as the HTTP method.
     *
     * @param string $method The HTTP method for this implementation
     *
     * @return ReflectionMethod The returned reflection object
     */
    public function getReflection($method)
    {
        $mirror = new ReflectionClass($this->class);

        if ($mirror->hasMethod($method)) {
            return new ReflectionMethod($this->class, $method);
        }
    }

    /**
     * Runs the class method when this route is matched with params
     *
     * @param string $method The HTTP method for this implementation
     * @param array  $params An array of params for this request
     *
     * @see Respect\Rest\Request::$params
     *
     * @return mixed Whatever the class method returns
     */
    public function runTarget($method, &$params)
    {
        if (is_null($this->instance)) {
            $this->instance = $this->createInstance();
        }

        return call_user_func_array(
            array($this->instance, $method),
            $params
        );
    }
}
