<?php

namespace Respect\Rest\Routes;

class ClassNameTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    public function test_getReflection_should_return_instance_of_current_routed_class()
    {
        $route = new ClassName('any', '/', 'DateTime');
        $refl = $route->getReflection('format');
        $this->assertInstanceOf('ReflectionMethod', $refl);
    }

    public function test_getReflection_should_return_instance_make_it_snap()
    {
        $route = new ClassName('any', '/', 'DateTime');
        $refl = $route->getReflection('oXoXoXoXoXo');
        $this->assertNull($refl);
    }
}
