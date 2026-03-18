<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\HttpFactories;
use Respect\Rest\Router;
use RuntimeException;

/** @covers Respect\Rest\Routes\Exception */
final class ExceptionTest extends TestCase
{
    /**
     * @covers Respect\Rest\Routes\Exception::getReflection
     * @covers Respect\Rest\Routes\Exception::runTarget
     * @covers Respect\Rest\Router::exceptionRoute
     */
    public function testMagicConstuctorCanCreateRoutesToExceptions(): void
    {
        $factory = new Psr17Factory();
        $router = new Router(new HttpFactories($factory, $factory));
        $called = false;
        $phpUnit = $this;
        $router->exceptionRoute('RuntimeException', static function ($e) use (&$called, $phpUnit) {
            $called = true;
            $phpUnit->assertEquals(
                'Oops',
                $e->getMessage(),
                'The exception message should be available in the exception route',
            );

            return 'There has been a runtime error.';
        });
        $router->get('/', static function (): void {
            throw new RuntimeException('Oops');
        });
        $resp = $router->dispatch(new ServerRequest('GET', '/'))->response();
        self::assertNotNull($resp);
        $response = (string) $resp->getBody();

        self::assertTrue($called, 'The exception route must have been called');

        self::assertEquals(
            'There has been a runtime error.',
            $response,
            'An exception should be caught by the router and forwarded',
        );
    }
}
