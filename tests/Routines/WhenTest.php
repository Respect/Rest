<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Respect\Rest\DispatchContext;
use Respect\Rest\Router;
use Respect\Rest\Routines\When;
use Respect\Rest\Test\Stubs\WhenAlwaysTrue;

use function strlen;

/** @covers Respect\Rest\Routines\When */
final class WhenTest extends TestCase
{
    public function testRoutineWhenShouldBlockRouteFromMatchIfTheCallbackReturnIsFalse(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/', static function () {
            return 'Oh yeah!';
        });
        $router->get('/', static function () {
            return 'Oh noes!';
        })->when(static function () {
            return false;
        });
        $resp = $router->dispatch(new ServerRequest('GET', '/'))->response();
        self::assertNotNull($resp);
        $response = (string) $resp->getBody();

        self::assertEquals(
            'Oh yeah!',
            $response,
            'For two identical routes, a failed When routine should not dispatch, the other one should',
        );

        self::assertNotEquals(
            'Oh noes!',
            $response,
            'For two identical routes, a failed When routine should not dispatch, the other one should',
        );
    }

    public function testRoutineWhenShouldConsiderSyncedCallbackParameters(): void
    {
        $phpUnit = $this;
        $router = new Router('', new Psr17Factory());
        $router->get('/speakers/*', static function ($speakerName) {
            return 'Hello ' . $speakerName;
        })->when(static function ($speakerName) use ($phpUnit) {
            $phpUnit->assertEquals('alganet', $speakerName);

            return strlen($speakerName) >= 3;
        });
        $resp = $router->dispatch(new ServerRequest('GET', '/speakers/alganet'))->response();
        self::assertNotNull($resp);
        $response = (string) $resp->getBody();

        self::assertEquals(
            'Hello alganet',
            $response,
            'This When routine accepts parameters longer than 3 chars, alganet is, so it should pass',
        );
    }

    /** @covers Respect\Rest\Routines\When::when */
    public function testWhen(): void
    {
        $context = new DispatchContext(
            new ServerRequest('GET', '/'),
            new Psr17Factory(),
        );
        $params = [];

        $when = new When(static function () {
                return true;
        });
        self::assertTrue($when->when($context, $params));

        $when = new When(static function () {
                return false;
        });
        self::assertFalse($when->when($context, $params));
    }

    public function test_when_with_a_callable_class_within_a_route(): void
    {
        $router  = new Router('', new Psr17Factory());
        $routine = new WhenAlwaysTrue();
        $router->get('/', static function () {
            return 'route';
        })
            ->by($routine);
        self::assertEquals(
            $expected = 'route',
            (string) $router->dispatch(new ServerRequest('GET', '/')),
        );
        $ref = new ReflectionObject($routine);
        $prop = $ref->getProperty('invoked');
        self::assertEquals(true, $prop->getValue($routine), 'Routine was not invoked!');
    }
}
