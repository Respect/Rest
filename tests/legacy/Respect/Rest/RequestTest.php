<?php

namespace Respect\Rest;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;

/**
 * @covers Respect\Rest\Request
 */
class LegacyRequestTest extends \PHPUnit\Framework\TestCase
{
    function setUp(): void 
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    function test_casting_to_string_returns_response()
    {
        $request = new Request(new ServerRequest('GET', '/'));
        $request->route = new Routes\Callback('GET', '/', function() {
            return 'ok';
        });
        $this->assertEquals('ok', (string) $request);
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
        $this->assertEquals([null,'bar',null], $args);
    }
}