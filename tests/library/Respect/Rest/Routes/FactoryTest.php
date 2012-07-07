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

    /**
     * @covers Respect\Rest\Routes\Factory::runTarget
     */
    function test_example_controller_by_factory()
    {
        $r = new Router();
        $r->get(
            '/*/*',
            __NAMESPACE__.'\\iController',
            array(
                __NAMESPACE__.'\\ControllerFactory',
                'route'
            )
        );

        $response = $r->dispatch('get', "/users/nickl")->response();
        $this->assertEquals(
            "Shifted by ref: 'users' and routed argument: 'nickl'",
            $response
        );
    }
}

interface iController extends Routable
{
    public function get($name);
}
class ControllerFactory
{
    public static function route($method, $params)
    {
        $shift = array_shift($params);
        return new Controller($shift);
    }
}
class Controller implements iController
{
    private $shifted;
    public function __construct($shifted)
    {
        $this->shifted = $shifted;
    }
    public function get($name)
    {
        return "Shifted by ref: '$this->shifted' and routed argument: '$name'";
    }
}
