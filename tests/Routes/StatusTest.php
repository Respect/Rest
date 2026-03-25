<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Rest\Router;

/** @covers Respect\Rest\Handlers\StatusHandler */
final class StatusTest extends TestCase
{
    public function testStatusRoute404(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/exists', static fn() => 'ok');
        $router->onStatus(404, static fn(ServerRequestInterface $r) => 'Not found: ' . $r->getUri()->getPath());

        $response = $router->handle(new ServerRequest('GET', '/nope'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('Not found: /nope', (string) $response->getBody());
    }

    public function testStatusRoute405(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/resource', static fn() => 'ok');
        $router->onStatus(405, static fn() => 'Method not allowed');

        $response = $router->handle(new ServerRequest('DELETE', '/resource'));

        self::assertSame(405, $response->getStatusCode());
        self::assertSame('Method not allowed', (string) $response->getBody());
        self::assertStringContainsString('GET', $response->getHeaderLine('Allow'));
    }

    public function testStatusRoute400FromWhenFailure(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/guarded', static fn() => 'ok')->when(static fn() => false);
        $router->onStatus(400, static fn() => 'Bad request');

        $response = $router->handle(new ServerRequest('GET', '/guarded'));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('Bad request', (string) $response->getBody());
    }

    public function testStatusRouteDoesNotInterfereWithNormalRoutes(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/hello', static fn() => 'world');
        $router->onStatus(404, static fn() => 'not found');

        $response = $router->handle(new ServerRequest('GET', '/hello'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('world', (string) $response->getBody());
    }

    public function testWithoutStatusRouteBareResponseIsReturned(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/exists', static fn() => 'ok');

        $response = $router->handle(new ServerRequest('GET', '/nope'));

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testStatusRouteReturnsArrayAsJson(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/exists', static fn() => 'ok');
        $router->onStatus(
            404,
            static fn(ServerRequestInterface $r) => ['error' => 'Not found', 'path' => $r->getUri()->getPath()],
        );

        $response = $router->handle(new ServerRequest('GET', '/missing'));

        self::assertSame(404, $response->getStatusCode());
        self::assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"error":"Not found","path":"\/missing"}', (string) $response->getBody());
    }

    public function testStatusRouteWorksViaDispatch(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/exists', static fn() => 'ok');
        $router->onStatus(404, static fn() => 'custom 404');

        $response = $router->dispatch(new ServerRequest('GET', '/nope'))->response();

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
        self::assertSame('custom 404', (string) $response->getBody());
    }
}
