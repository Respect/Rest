<?php
namespace Respect\Rest\Routes;

use ReflectionClass;

class Error extends Callback
{
    public $callback;
    public $errors = array();

    public function __construct($callback)
    {
        parent::__construct('ANY', '^$', $callback);
    }

    public function runTarget($method, &$params)
    {
        return call_user_func($this->callback, $this->errors);
    }
}
