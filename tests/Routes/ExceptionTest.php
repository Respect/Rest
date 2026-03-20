<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PDOException;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Router;
use RuntimeException;
use Throwable;

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
        $router = new Router('', new Psr17Factory());
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

    public function testExceptionRouteCatchesSubclassViaInheritance(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->exceptionRoute('RuntimeException', static fn($e) => 'caught: ' . $e->getMessage());
        $router->get('/', static function (): void {
            throw new PDOException('db error');
        });

        $resp = $router->dispatch(new ServerRequest('GET', '/'))->response();
        self::assertNotNull($resp);
        self::assertSame('caught: db error', (string) $resp->getBody());
    }

    public function testThrowableExceptionRouteCatchesAll(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->exceptionRoute('Throwable', static fn(Throwable $e) => 'caught: ' . $e::class);
        $router->get('/', static function (): void {
            throw new RuntimeException('test');
        });

        $resp = $router->dispatch(new ServerRequest('GET', '/'))->response();
        self::assertNotNull($resp);
        self::assertSame('caught: RuntimeException', (string) $resp->getBody());
    }

    public function testExceptionRouteWorksViaHandle(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->exceptionRoute('Throwable', static fn(Throwable $e) => 'handled: ' . $e->getMessage());
        $router->get('/', static function (): void {
            throw new RuntimeException('boom');
        });

        $response = $router->handle(new ServerRequest('GET', '/'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('handled: boom', (string) $response->getBody());
    }
}
