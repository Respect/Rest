<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Router;

/**
 * @covers Respect\Rest\Routes\Exception
 */
final class ExceptionTest extends TestCase
{
    /**
     * @covers Respect\Rest\Routes\Exception::getReflection
     * @covers Respect\Rest\Routes\Exception::runTarget
     * @covers Respect\Rest\Router::exceptionRoute
     */
    public function testMagicConstuctorCanCreateRoutesToExceptions()
    {
        $router = new Router(new Psr17Factory());
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
        $response = (string) $router->dispatch(new ServerRequest('GET', '/'))->response()->getBody();

        self::assertTrue($called, 'The exception route must have been called');

        self::assertEquals(
            'There has been a runtime error.',
            $response,
            'An exception should be caught by the router and forwarded'
        );
    }
}
