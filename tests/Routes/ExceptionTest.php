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

/** @covers Respect\Rest\Handlers\ExceptionHandler */
final class ExceptionTest extends TestCase
{
    /**
     * @covers Respect\Rest\Handlers\ExceptionHandler::getReflection
     * @covers Respect\Rest\Handlers\ExceptionHandler::runTarget
     * @covers Respect\Rest\Router::onException
     */
    public function testMagicConstuctorCanCreateRoutesToExceptions(): void
    {
        $router = new Router('', new Psr17Factory());
        $called = false;
        $phpUnit = $this;
        $router->onException('RuntimeException', static function ($e) use (&$called, $phpUnit) {
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
        $router->onException('RuntimeException', static fn($e) => 'caught: ' . $e->getMessage());
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
        $router->onException('Throwable', static fn(Throwable $e) => 'caught: ' . $e::class);
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
        $router->onException('Throwable', static fn(Throwable $e) => 'handled: ' . $e->getMessage());
        $router->get('/', static function (): void {
            throw new RuntimeException('boom');
        });

        $response = $router->handle(new ServerRequest('GET', '/'));
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('handled: boom', (string) $response->getBody());
    }

    public function testExceptionStateDoesNotLeakBetweenDispatches(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->onException('Throwable', static fn(Throwable $e) => 'error: ' . $e->getMessage());
        $router->get('/fail', static function (): void {
            throw new RuntimeException('first');
        });
        $router->get('/ok', static fn() => 'success');

        // First dispatch throws
        $resp1 = $router->handle(new ServerRequest('GET', '/fail'));
        self::assertSame('error: first', (string) $resp1->getBody());

        // Second dispatch should not see the first exception
        $resp2 = $router->handle(new ServerRequest('GET', '/ok'));
        self::assertSame('success', (string) $resp2->getBody());
    }
}
