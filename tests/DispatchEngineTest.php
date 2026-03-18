<?php

declare(strict_types=1);

namespace Respect\Rest\Test;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchEngine;
use Respect\Rest\HttpFactories;
use Respect\Rest\RouteProvider;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routes\Callback;
use Respect\Rest\Routes\StaticValue;
use RuntimeException;

/** @covers Respect\Rest\DispatchEngine */
final class DispatchEngineTest extends TestCase
{
    private HttpFactories $httpFactories;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->httpFactories = new HttpFactories($this->factory, $this->factory);
    }

    public function testMatchingRouteConfiguresContext(): void
    {
        $engine = $this->engine([
            new StaticValue('GET', '/hello', 'world'),
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
            new StaticValue('GET', '/exists', 'ok'),
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
            new StaticValue('GET', '/resource', 'ok'),
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
            new StaticValue('GET', '/a', 'ok'),
            new StaticValue('POST', '/b', 'ok'),
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
            new StaticValue('GET', '/resource', 'ok'),
            new StaticValue('POST', '/resource', 'ok'),
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
            new StaticValue('GET', '/resource', 'found'),
        ]);
        $provider->method('getBasePath')->willReturn('/api');

        $engine = new DispatchEngine(
            $provider,
            $this->httpFactories->responses,
            $this->httpFactories->streams,
        );

        $context = $engine->dispatch(new ServerRequest('GET', '/api/resource'));

        $response = $context->response();
        self::assertNotNull($response);
        self::assertSame('found', (string) $response->getBody());
    }

    public function testHandleReturnsPsr7Response(): void
    {
        $engine = $this->engine([
            new StaticValue('GET', '/hello', 'world'),
        ]);

        $response = $engine->handle(new ServerRequest('GET', '/hello'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('world', (string) $response->getBody());
    }

    public function testHandleReturns500OnException(): void
    {
        $engine = $this->engine([
            new Callback('GET', '/boom', static function (): never {
                throw new RuntimeException('fail');
            }),
        ]);

        $response = $engine->handle(new ServerRequest('GET', '/boom'));

        self::assertSame(500, $response->getStatusCode());
    }

    public function testOnContextReadyCallbackIsInvoked(): void
    {
        $captured = null;
        $provider = $this->createStub(RouteProvider::class);
        $provider->method('getRoutes')->willReturn([
            new StaticValue('GET', '/test', 'ok'),
        ]);
        $provider->method('getBasePath')->willReturn(null);

        $engine = new DispatchEngine(
            $provider,
            $this->httpFactories->responses,
            $this->httpFactories->streams,
            static function ($context) use (&$captured): void {
                $captured = $context;
            },
        );

        $context = $engine->dispatch(new ServerRequest('GET', '/test'));

        self::assertSame($context, $captured);
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
        $provider->method('getBasePath')->willReturn(null);

        return new DispatchEngine(
            $provider,
            $this->httpFactories->responses,
            $this->httpFactories->streams,
        );
    }
}
