<?php
namespace Respect\Rest\Routines;

use Respect\Rest\Request,
    Respect\Rest\Router;
use Stubs\Routines\ByClassWithInvoke;

/**
 * @covers Respect\Rest\Routines\By
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class ByTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new By(function () {
              return 'from by callback';
            });
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        unset($this->object);
    }

    /**
     * @covers Respect\Rest\Routines\By::by
     */
    public function test_by_with_an_anonymous_function()
    {
        $request = new Request();
        $params  = array();
        $routine = new By(function() { return 'from by callback'; });
        $this->assertEquals('from by callback', $routine->by($request, $params));
    }

    /**
     * @covers Respect\Rest\Routines\By
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     */
    public function test_by_on_a_route()
    {
        $router = new Router();
        $router->get('/', function() { return 'route'; })
               ->by(function() { return 'by'; });
        // By does not affect the output of the route.
        $this->assertEquals(
            $expected = 'route',
            (string) $router->dispatch('GET', '/')
        );
    }

    /**
     * @covers Respect\Rest\Routines\By
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     */
    public function test_by_on_a_route_with_classname()
    {
        $router = new Router();
        $router->get('/', function() { return 'route'; })
               ->by('Stubs\Routines\ByClassWithInvoke');
        // By does not affect the output of the route.
        $this->assertEquals(
            $expected = 'route',
            (string) $router->dispatch('GET', '/')
        );
    }

    /**
     * @covers Respect\Rest\Routines\By
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     */
    public function test_by_with_a_callable_class_on_a_route()
    {
        $router  = new Router;
        $routine = new ByClassWithInvoke;
        $router->get('/', function() { return 'route'; })
               ->by($routine);
        // By does not affect the output of the route.
        $this->assertEquals(
            $expected = 'route',
            (string) $router->dispatch('GET', '/')
        );
        $this->assertAttributeEquals(
            $expected = true,
            'invoked',
            $routine,
            'Routine was not invoked!'
        );
    }
}
