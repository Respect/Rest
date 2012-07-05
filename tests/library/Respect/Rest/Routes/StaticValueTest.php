<?php
namespace Respect\Rest\Routes;

/**
 * @covers Respect\Rest\Routes\StaticValue
 */
class StaticValueTest extends \PHPUnit_Framework_TestCase
{
    function setUp() 
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }
    /**
     * @covers Respect\Rest\Routes\StaticValue::getReflection
     */
    function test_getReflection_should_return_instance_of_current_routed_class()
    {
        $route = new StaticValue('any', '/', array('foo'));
        $refl = $route->getReflection('format');
        $this->assertInstanceOf('ReflectionMethod', $refl);
    }
    /**
     * @covers Respect\Rest\Routes\StaticValue::runTarget
     */
    function test_runTarget_returns_value()
    {
        $route = new StaticValue('any', '/', array('foo'));
        $p=array('');
        $this->assertEquals(array('foo'), $route->runTarget('get', $p));
    }
}