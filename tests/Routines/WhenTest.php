<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Request;
use Respect\Rest\Router;
use Respect\Rest\Routines\When;
use Respect\Rest\Test\Stubs\WhenAlwaysTrue;

/**
 * @covers Respect\Rest\Routines\When
 */
final class WhenTest extends TestCase
{
    public function testRoutineWhenShouldBlockRouteFromMatchIfTheCallbackReturnIsFalse()
    {
        $router = new Router(new Psr17Factory());
        $router->get('/', function () {
            return 'Oh yeah!';
        });
        $router->get('/', function () {
            return 'Oh noes!';
        })->when(function () {
            return false;
        });
        $response = (string) $router->dispatch(new ServerRequest('GET', '/'))->response()->getBody();

        self::assertEquals(
            'Oh yeah!',
            $response,
            'For two identical routes, a failed When routine should not dispatch, the other one should'
        );

        self::assertNotEquals(
            'Oh noes!',
            $response,
            'For two identical routes, a failed When routine should not dispatch, the other one should'
        );
    }

    public function testRoutineWhenShouldConsiderSyncedCallbackParameters()
    {
        $phpUnit = $this;
        $router = new Router(new Psr17Factory());
        $router->get('/speakers/*', function ($speakerName) {
            return "Hello $speakerName";
        })->when(function ($speakerName) use ($phpUnit) {
            $phpUnit->assertEquals('alganet', $speakerName);
            return strlen($speakerName) >= 3;
        });
        $response = (string) $router->dispatch(new ServerRequest('GET', '/speakers/alganet'))->response()->getBody();

        self::assertEquals(
            'Hello alganet',
            $response,
            'This When routine accepts parameters longer than 3 chars, alganet is, so it should pass'
        );
    }

    /**
     * @covers Respect\Rest\Routines\When::when
     */
    public function testWhen()
    {
        $request = new Request(new ServerRequest('GET', '/'));
        $params = [];

        $when = new When(function () {
                return true;
            });
        self::assertTrue($when->when($request, $params));

        $when = new When(function () {
                return false;
            });
        self::assertFalse($when->when($request, $params));
    }

    public function test_when_with_a_callable_class_within_a_route()
    {
        $router  = new Router(new Psr17Factory());
        $routine = new WhenAlwaysTrue;
        $router->get('/', function() { return 'route'; })
               ->by($routine);
        self::assertEquals(
            $expected = 'route',
            (string) $router->dispatch(new ServerRequest('GET', '/'))
        );
        $ref = new \ReflectionObject($routine);
        $prop = $ref->getProperty('invoked');
        self::assertEquals(true, $prop->getValue($routine), 'Routine was not invoked!');
    }
}
