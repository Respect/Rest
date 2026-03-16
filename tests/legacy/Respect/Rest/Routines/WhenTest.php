<?php
declare(strict_types=1);

namespace Respect\Rest\Routines {

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Respect\Rest\Request,
    Respect\Rest\Router;
use Stubs\Routines\WhenAlwaysTrue;

/**
 * @covers Respect\Rest\Routines\When
 * @author Nick Lombard <github@jigsoft.co.za>
 */
final class LegacyWhenTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var When
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new When(function () {
                return true;
            });
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
    }

    /**
     * @covers Respect\Rest\Routines\When::when
     */
    public function testWhen()
    {
        global $header;
        $header = [];
        $request = new Request(new ServerRequest('GET', '/'));
        $params = [];
        $alias = &$this->object;

        self::assertTrue($alias->when($request, $params));
        self::assertCount(0, $header);

        $this->object = new When(function () {
                return false;
            });
        $alias = &$this->object;

        self::assertFalse($alias->when($request, $params));
    }

    public function test_when_with_a_callable_class_within_a_route()
    {
        $router  = new Router(new Psr17Factory());
        $routine = new WhenAlwaysTrue;
        $router->get('/', function() { return 'route'; })
               ->by($routine);
        // By does not affect the output of the route.
        self::assertEquals(
            $expected = 'route',
            (string) $router->dispatch(new ServerRequest('GET', '/'))
        );
        $ref = new \ReflectionObject($routine);
        $prop = $ref->getProperty('invoked');
        self::assertEquals(true, $prop->getValue($routine), 'Routine was not invoked!');
    }
}

    if (!function_exists(__NAMESPACE__.'\\header')) {
        function header($string, $replace=true, $http_response_code=200)
        {
            global $header;
            if (!$replace && isset($header))
                return;

            $header[$string] = $string;
        }
    }
}

namespace Respect\Rest {
    if (!function_exists(__NAMESPACE__.'\\header')) {
        function header($string, $replace=true, $http_response_code=200)
        {
            global $header;
            if (!$replace && isset($header))
                return;

            $header[$string] = $string;
        }
    }
}

namespace {
    $header=[];
}
