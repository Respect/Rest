<?php
namespace Respect\Rest\Routes;

use ReflectionClass;

class Error extends AbstractRoute
{
    public $callback;
    public $errors = array();

    public function __construct($callback)
    {
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
        return call_user_func($this->callback, $this->errors);
    }
}