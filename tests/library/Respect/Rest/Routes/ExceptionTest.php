<?php
namespace Respect\Rest\Routes;

use PHPUnit_Framework_TestCase;
use Respect\Rest\Router;

/** 
 * @covers Respect\Rest\Routes\Exception
 */
class ExceptionTest extends PHPUnit_Framework_TestCase
{
    /**
     * @covers Respect\Rest\Routes\Exception::getReflection
     * @covers Respect\Rest\Routes\Exception::runTarget
     * @covers Respect\Rest\Router::exceptionRoute
     */
    public function testMagicConstuctorCanCreateRoutesToExceptions()
    {
        $router = new Router;
        $called = false;
        $phpUnit = $this;
        $router->exceptionRoute('RuntimeException', function ($e) use (&$called, $phpUnit) {
            $called = true;
            $phpUnit->assertEquals(
                'Oops',
                $e->getMessage(),
                'The exception message should be available in the exception route'
            );
            return 'There has been a runtime error.';
        });
        $router->get('/', function () {
            throw new \RuntimeException('Oops');
        });
        $response = (string) $router->dispatch('GET', '/')->response();

        $this->assertTrue($called, 'The exception route must have been called');

        $this->assertEquals(
            'There has been a runtime error.',
            $response,
            'An exception should be caught by the router and forwarded'
        );
    }

}