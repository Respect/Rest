<?php

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Respect\Rest\Routable;

class ClassName extends AbstractRoute
{

    public $class = '';
    public $constructorParams = array();
    protected $instance = null;

    public function __construct($method, $pattern, $class, array $constructorParams=array())
    {
        $this->class = $class;
        $this->constructorParams = $constructorParams;
        parent::__construct($method, $pattern);
    }

    protected function createInstance()
    {
        $className = $this->class;

        $reflection = new ReflectionClass($className);
        if (!$reflection->implementsInterface('Respect\\Rest\\Routable'))
            throw new InvalidArgumentException('Routed classes must implement the Respect\\Rest\\Routable interface'); 

            if (empty($this->constructorParams) || !method_exists($this->class,
                '__construct'))
            return new $className;

        $reflection = new ReflectionClass($this->class);
        return $reflection->newInstanceArgs($this->constructorParams);
    }

    public function getReflection($method)
    {
        $mirror = new ReflectionClass($this->class);
        if ($mirror->hasMethod($method))
            return new ReflectionMethod($this->class, $method);
    }

    public function runTarget($method, &$params)
    {
        if (is_null($this->instance))
            $this->instance = $this->createInstance();

        return call_user_func_array(
            array($this->instance, $method), $params
        );
    }

}
