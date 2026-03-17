<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use Respect\Rest\Request;
use Respect\Rest\Router;
use Respect\Rest\Routines\By;
use Respect\Rest\Test\Stubs\ByClassWithInvoke;

/** @covers Respect\Rest\Routines\By */
final class ByTest extends TestCase
{
    private By $object;

    protected function setUp(): void
    {
        $this->object = new By(static function () {
              return 'from by callback';
        });
    }

    /** @covers Respect\Rest\Routines\By::by */
    public function test_by_with_an_anonymous_function(): void
    {
        $request = new Request(new ServerRequest('GET', '/'));
        $params  = [];
        $routine = new By(static function () {
            return 'from by callback';
        });
        self::assertEquals('from by callback', $routine->by($request, $params));
    }

    /**
     * @covers Respect\Rest\Routines\By
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     */
    public function test_by_on_a_route(): void
    {
        $router = new Router(new Psr17Factory());
        $router->get('/', static function () {
            return 'route';
        })
            ->by(static function () {
                return 'by';
            });
        self::assertEquals(
            $expected = 'route',
            (string) $router->dispatch(new ServerRequest('GET', '/')),
        );
    }

    /**
     * @covers Respect\Rest\Routines\By
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     */
    public function test_by_on_a_route_with_classname(): void
    {
        $router = new Router(new Psr17Factory());
        $router->get('/', static function () {
            return 'route';
        })
            ->by('Respect\Rest\Test\Stubs\ByClassWithInvoke');
        self::assertEquals(
            $expected = 'route',
            (string) $router->dispatch(new ServerRequest('GET', '/')),
        );
    }

    /**
     * @covers Respect\Rest\Routines\By
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     */
    public function test_by_with_a_callable_class_on_a_route(): void
    {
        $router  = new Router(new Psr17Factory());
        $routine = new ByClassWithInvoke();
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

    protected function tearDown(): void
    {
        unset($this->object);
    }
}
