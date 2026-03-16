<?php
namespace Respect\Rest\Routes;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Router;

/**
 * @covers Respect\Rest\Routes\Error
 */
class ErrorTest extends TestCase
{
    /**
     * @covers Respect\Rest\Routes\Error::getReflection
     * @covers Respect\Rest\Routes\Error::runTarget
     * @covers Respect\Rest\Router::errorRoute
     */
    #[RunInSeparateProcess]
    public function testMagicConstuctorCanCreateRoutesToErrors()
    {
        $router = new Router(new Psr17Factory());
        $called = false;
        $phpUnit = $this;
        $router->errorRoute(function ($err) use (&$called, $phpUnit) {
            $called = true;
            $phpUnit->assertContains(
                'Oops',
                $err[0],
                'The error message should be available in the error route'
            );
            return 'There has been an error.';
        });
        $router->get('/', function () {
            trigger_error('Oops', E_USER_WARNING);
        });
        $response = (string) $router->dispatch(new ServerRequest('GET', '/'))->response()->getBody();

        $this->assertTrue($called, 'The error route must have been called');

        $this->assertEquals(
            'There has been an error.',
            $response,
            'An error should be caught by the router and forwarded'
        );
    }
}
