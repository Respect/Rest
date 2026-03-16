<?php
declare(strict_types=1);

namespace Respect\Rest;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * @covers Respect\Rest\Request
 */
final class LegacyRequestTest extends \PHPUnit\Framework\TestCase
{
    function test_casting_to_string_returns_response()
    {
        $request = new Request(new ServerRequest('GET', '/'));
        $route = new Routes\Callback('GET', '/', function() {
            return 'ok';
        });
        $route->responseFactory = new Psr17Factory();
        $request->route = $route;
        self::assertEquals('ok', (string) $request);
    }

    function test_unsynced_param_comes_as_null()
    {
        $request = new Request(new ServerRequest('GET', '/'));
        $request->route = new Routes\Callback('GET', '/', function($bar) {
            return 'ok';
        });
        $args = [];
        $request->route->appendRoutine($routine = new Routines\By(function($foo, $bar, $baz) use (&$args){
            $args = func_get_args();
        }));
        $dummy=['bar'];
        $request->routineCall('by', 'GET', $routine, $dummy);
        self::assertEquals([null,'bar',null], $args);
    }
}