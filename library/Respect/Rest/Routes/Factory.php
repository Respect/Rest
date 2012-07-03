<?php

namespace Respect\Rest\Routes;

use ReflectionMethod;
use InvalidArgumentException;
use Respect\Rest\Routable;

class Factory extends AbstractRoute
{

    public $class = '';
    protected $instance = null;
    protected $factory = null;

    /** @var ReflectionMethod */
    protected $reflection;

    public function __construct($method, $pattern, $class, $factory)
    {
        $this->factory = $factory;
        $this->class = $class;
        parent::__construct($method, $pattern);
    }

    public function getReflection($method)
    {
        if (empty($this->reflection))
            $this->reflection = new ReflectionMethod(
                $this->class, $method
            );

        return $this->reflection;
    }

    public function runTarget($method, &$params)
    {
        if (is_null($this->instance))
            $this->instance = call_user_func($this->factory);

        if (!$this->instance instanceof Routable)
            throw new InvalidArgumentException('Routed classes must implement the Respect\\Rest\\Routable interface');

        return call_user_func_array(
                array($this->instance, $method), $params
        );
    }

}
