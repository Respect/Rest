<?php
namespace Respect\Rest\Routes;

use ReflectionClass;

class Exception extends Callback
{
    public $class;
    public $callback;
    public $exception;

    public function __construct($class, $callback)
    {
        $this->class = $class;
        parent::__construct('ANY', '^$', $callback);
    }

    public function runTarget($method, &$params)
    {
        return call_user_func($this->callback, $this->exception);
    }
}
