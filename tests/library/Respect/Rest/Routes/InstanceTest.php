<?php

namespace Respect\Rest\Routes;

class InstanceTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    public function test_getReflection_should_return_instance_of_current_routed_class()
    {
        $route = new Instance('any', '/', new \DateTime);
        $refl = $route->getReflection('format');
        $this->assertInstanceOf('ReflectionMethod', $refl);
    }
}
