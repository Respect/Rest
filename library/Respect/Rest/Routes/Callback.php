<?php

namespace Respect\Rest\Routes;

use ReflectionFunction;
use ReflectionMethod;

/** A callback-based route */
class Callback extends AbstractRoute
{

    protected $callback;
    /** @var ReflectionFunctionAbstract */
    protected $reflection;

    public function __construct($method, $pattern, $callback)
    {
        $this->callback = $callback;
        parent::__construct($method, $pattern);
    }

    /** Returns an appropriate Reflection for any is_callable object */
    public function getCallbackReflection()
    {
        if (is_array($this->callback))
            return new ReflectionMethod($this->callback[0], $this->callback[1]);
        else
            return new ReflectionFunction($this->callback);
    }

    public function getReflection($method)
    {
        if (empty($this->reflection))
            $this->reflection = $this->getCallbackReflection();

        return $this->reflection;
    }

    public function runTarget($method, &$params)
    {
        return call_user_func_array($this->callback, $params);
    }

}