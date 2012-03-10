<?php

namespace Respect\Rest\Routes;

class ClassNameTest extends \PHPUnit_Framework_TestCase
{
    function setUp() 
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    function test_getReflection_should_return_instance_of_current_routed_class()
    {
        $route = new ClassName('any', '/', 'DateTime');
        $refl = $route->getReflection('format');
        $this->assertInstanceOf('ReflectionMethod', $refl);
    }
}