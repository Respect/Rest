<?php

declare(strict_types=1);

namespace Respect\Rest\Test;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Fluent\Resolvers\Ucfirst;
use Respect\Rest\DispatchEngine;
use Respect\Rest\RouteProvider;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routes\Callback;
use Respect\Rest\Routes\StaticValue;
use Respect\Rest\Routines\Routinable;
use RuntimeException;

/** @covers Respect\Rest\DispatchEngine */
final class DispatchEngineTest extends TestCase
{
    private Psr17Factory $factory;

    private NamespaceLookup $lookup;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->lookup = new NamespaceLookup(new Ucfirst(), Routinable::class, 'Respect\\Rest\\Routines');
    }

    public function testMatchingRouteConfiguresContext(): void
    {
        $engine = $this->engine([
            new StaticValue($this->lookup, 'GET', '/hello', 'world'),
        ]);

        $context = $engine->dispatch(new ServerRequest('GET', '/hello'));

        self::assertNotNull($context->route);
        $response = $context->response();
        self::assertNotNull($response);
        self::assertSame('world', (string) $response->getBody());
    }

    public function testNoMatchReturns404(): void
    {
        $engine = $this->engine([
            new StaticValue($this->lookup, 'GET', '/exists', 'ok'),
        ]);

        $context = $engine->dispatch(new ServerRequest('GET', '/not-found'));

        self::assertTrue($context->hasPreparedResponse());
        $response = $context->response();
        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
    }

    public function testWrongMethodReturns405WithAllowHeader(): void
    {
        $engine = $this->engine([
            new StaticValue($this->lookup, 'GET', '/resource', 'ok'),
        ]);

        $context = $engine->dispatch(new ServerRequest('DELETE', '/resource'));

        self::assertTrue($context->hasPreparedResponse());
        $response = $context->response();
        self::assertNotNull($response);
        self::assertSame(405, $response->getStatusCode());
        self::assertStringContainsString('GET', $response->getHeaderLine('Allow'));
    }

    public function testGlobalOptionsReturns204WithAllMethods(): void
    {
        $engine = $this->engine([
            new StaticValue($this->lookup, 'GET', '/a', 'ok'),
            new StaticValue($this->lookup, 'POST', '/b', 'ok'),
        ]);

        $context = $engine->dispatch(new ServerRequest('OPTIONS', '*'));

        self::assertTrue($context->hasPreparedResponse());
        $response = $context->response();
        self::assertNotNull($response);
        self::assertSame(204, $response->getStatusCode());
        $allow = $response->getHeaderLine('Allow');
        self::assertStringContainsString('GET', $allow);
        self::assertStringContainsString('POST', $allow);
        self::assertStringContainsString('OPTIONS', $allow);
    }

    public function testOptionsOnSpecificPathReturns204(): void
    {
        $engine = $this->engine([
            new StaticValue($this->lookup, 'GET', '/resource', 'ok'),
            new StaticValue($this->lookup, 'POST', '/resource', 'ok'),
        ]);

        $context = $engine->dispatch(new ServerRequest('OPTIONS', '/resource'));

        self::assertTrue($context->hasPreparedResponse());
        $response = $context->response();
        self::assertNotNull($response);
        self::assertSame(204, $response->getStatusCode());
        $allow = $response->getHeaderLine('Allow');
        self::assertStringContainsString('GET', $allow);
        self::assertStringContainsString('POST', $allow);
    }

    public function testBasePathPrefixIsStripped(): void
    {
        $provider = $this->createStub(RouteProvider::class);
        $provider->method('getRoutes')->willReturn([
            new StaticValue($this->lookup, 'GET', '/resource', 'found'),
        ]);
        $provider->method('getBasePath')->willReturn('/api');

        $engine = new DispatchEngine(
            $provider,
            $this->factory,
        );

        $context = $engine->dispatch(new ServerRequest('GET', '/api/resource'));

        $response = $context->response();
        self::assertNotNull($response);
        self::assertSame('found', (string) $response->getBody());
    }

    public function testHandleReturnsPsr7Response(): void
    {
        $engine = $this->engine([
            new StaticValue($this->lookup, 'GET', '/hello', 'world'),
        ]);

        $response = $engine->handle(new ServerRequest('GET', '/hello'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('world', (string) $response->getBody());
    }

    public function testHandlePropagatesUnhandledExceptions(): void
    {
        $engine = $this->engine([
            new Callback($this->lookup, 'GET', '/boom', static function (): never {
                throw new RuntimeException('fail');
            }),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('fail');
        $engine->handle(new ServerRequest('GET', '/boom'));
    }

    public function testGlobalOptions404WhenNoRoutes(): void
    {
        $engine = $this->engine([]);

        $context = $engine->dispatch(new ServerRequest('OPTIONS', '*'));

        self::assertTrue($context->hasPreparedResponse());
        $response = $context->response();
        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
    }

    /** @param array<int, AbstractRoute> $routes */
    private function engine(array $routes): DispatchEngine
    {
        $provider = $this->createStub(RouteProvider::class);
        $provider->method('getRoutes')->willReturn($routes);
        $provider->method('getBasePath')->willReturn('');

        return new DispatchEngine(
            $provider,
            $this->factory,
        );
    }
}
