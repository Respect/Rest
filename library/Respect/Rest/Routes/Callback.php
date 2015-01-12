<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routes;

use ReflectionFunction;
use ReflectionMethod;

/** A callback-based route */
class Callback extends AbstractRoute
{
    /** @var callable The actual callback this route holds */
    protected $callback;

    /** @var array String argument parameters from the Request */
    public $arguments;

    /** @var ReflectionFunctionAbstract The reflection for the callback */
    protected $reflection;

    /**
     * @param string   $method    The HTTP method (GET, POST, etc)
     * @param string   $pattern   The URI pattern for this route
     * @param callable $callback  The callback this route holds
     * @param array    $arguments Additional arguments for this callback
     */
    public function __construct(
        $method,
        $pattern,
        $callback,
        array $arguments = array()
    ) {
        $this->callback = $callback;
        $this->arguments = $arguments;
        parent::__construct($method, $pattern);
    }

    /**
     * Returns an appropriate Reflection for any callable object
     *
     * @return ReflectionFunctionAbstract The returned reflection object
     */
    public function getCallbackReflection()
    {
        if (is_array($this->callback)) {
            return new ReflectionMethod($this->callback[0], $this->callback[1]);
        } else {
            return new ReflectionFunction($this->callback);
        }
    }

    /**
     * Gets the reflection for a specific method. For callables, the reflection
     * is always the same. This follows the AbstractRoute implementation
     *
     * @param string $method The irrelevant HTTP method for this implementation
     *
     * @return ReflectionFunctionAbstract The returned reflection object
     */
    public function getReflection($method)
    {
        if (empty($this->reflection)) {
            $this->reflection = $this->getCallbackReflection();
        }

        return $this->reflection;
    }

    /**
     * Runs the callback when this route is matched with params
     *
     * @param string $method The irrelevant HTTP method for this implementation
     * @param array  $params An array of params for this request
     *
     * @see Respect\Rest\Request::$params
     *
     * @return mixed Whatever the callback returns
     */
    public function runTarget($method, &$params)
    {
        return call_user_func_array(
            $this->callback,
            array_merge($params, $this->arguments)
        );
    }
}
