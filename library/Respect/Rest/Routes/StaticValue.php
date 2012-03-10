<?php

namespace Respect\Rest\Routes;

use ReflectionMethod;

/** A callback-based route */
class StaticValue extends AbstractRoute
{

    protected $value;
    /** @var ReflectionFunctionAbstract */
    protected $reflection;

    public function __construct($method, $pattern, $value)
    {
        $this->value = $value;
        parent::__construct($method, $pattern);
        $this->reflection = new ReflectionMethod($this, 'returnValue');
    }
    
    public function getReflection($method)
    {
        return $this->reflection;
    }

    public function runTarget($method, &$params)
    {
        return $this->returnValue($method, $params);
    }
    
    public function returnValue() 
    {
        return $this->value;
    }

}