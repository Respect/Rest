<?php
namespace Respect\Rest\Routes;

use \Respect\Rest\Routable;
use \Respect\Rest\Router;

/**
 * @covers Respect\Rest\Routes\Factory
 */
class FactoryTest extends \PHPUnit_Framework_TestCase
{
    function setUp() 
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    /**
     * @covers Respect\Rest\Routes\Factory::getReflection
     */
    function test_getReflection_should_return_instance_of_current_routed_class()
    {
        $route = new Factory('any', '/', 'DateTime', function() {return new \DateTime;});
        $refl = $route->getReflection('format');
        $this->assertInstanceOf('ReflectionMethod', $refl);
    }
}
