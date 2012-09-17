<?php
namespace Respect\Rest\Routes;

use ReflectionClass;

class Exception extends AbstractRoute
{
    public $class;
    public $callback;
    public $exception;

    public function __construct($class, $callback)
    {
        $this->class = $class;
        $this->callback = $callback;
        parent::__construct('ANY', '^$');
    }

    public function getReflection($method)
    {
        if (empty($this->reflection))
            $this->reflection = new ReflectionClass('stdClass');

        return $this->reflection;
    }

    public function runTarget($method, &$params)
    {
        return call_user_func($this->callback, $this->exception);
    }
}