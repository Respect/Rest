<?php

declare(strict_types=1);

namespace Respect\Rest\Test;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Rest\Router;

use function func_get_args;
use function implode;
use function strtoupper;

/**
 * Tests that route callbacks and routines can type-hint PSR-7 interfaces
 * to receive automatic injection of ServerRequestInterface and ResponseInterface.
 */
final class Psr7InjectionTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router('', new Psr17Factory());
    }

    #[Test]
    public function callbackReceivesServerRequestWhenTypeHinted(): void
    {
        $this->router->get('/users/*', static function (string $name, ServerRequestInterface $request) {
            return $name . ':' . $request->getHeaderLine('X-Custom');
        });

        $serverRequest = (new ServerRequest('GET', '/users/alice'))
            ->withHeader('X-Custom', 'hello');
        $response = $this->router->dispatch($serverRequest)->response();

        self::assertNotNull($response);
        self::assertEquals('alice:hello', (string) $response->getBody());
    }

    #[Test]
    public function callbackReceivesResponseInterfaceWhenTypeHinted(): void
    {
        $this->router->get('/download/*', static function (string $file, ResponseInterface $response) {
            return $response
                ->withHeader('Content-Type', 'application/octet-stream')
                ->withHeader('Content-Disposition', 'attachment; filename="' . $file . '"');
        });

        $response = $this->router->dispatch(new ServerRequest('GET', '/download/report.pdf'))->response();

        self::assertNotNull($response);
        self::assertEquals('application/octet-stream', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('report.pdf', $response->getHeaderLine('Content-Disposition'));
    }

    #[Test]
    public function callbackReceivesBothRequestAndResponse(): void
    {
        $this->router->get('/echo', static function (ServerRequestInterface $req, ResponseInterface $res) {
            $body = $req->getHeaderLine('X-Echo');
            $res->getBody()->write($body);

            return $res->withHeader('X-Echoed', 'true');
        });

        $serverRequest = (new ServerRequest('GET', '/echo'))
            ->withHeader('X-Echo', 'ping');
        $response = $this->router->dispatch($serverRequest)->response();

        self::assertNotNull($response);
        self::assertEquals('ping', (string) $response->getBody());
        self::assertEquals('true', $response->getHeaderLine('X-Echoed'));
    }

    #[Test]
    public function callbackWithoutTypeHintsStillWorks(): void
    {
        $this->router->get('/simple/*', static function ($name) {
            return 'Hello, ' . $name . '!';
        });

        $response = $this->router->dispatch(new ServerRequest('GET', '/simple/world'))->response();

        self::assertNotNull($response);
        self::assertEquals('Hello, world!', (string) $response->getBody());
    }

    #[Test]
    public function callbackWithNoParametersStillReceivesUrlParams(): void
    {
        $this->router->get('/variadic/*', static function () {
            return implode(',', func_get_args());
        });

        $response = $this->router->dispatch(new ServerRequest('GET', '/variadic/a'))->response();

        self::assertNotNull($response);
        self::assertEquals('a', (string) $response->getBody());
    }

    #[Test]
    public function psrParametersCanAppearAnywhere(): void
    {
        $this->router->get('/mixed/*/*', static function (
            string $first,
            ServerRequestInterface $req,
            string $second,
            ResponseInterface $res,
        ) {
            $res->getBody()->write($first . '-' . $second . '-' . $req->getMethod());

            return $res;
        });

        $response = $this->router->dispatch(new ServerRequest('GET', '/mixed/a/b'))->response();

        self::assertNotNull($response);
        self::assertEquals('a-b-GET', (string) $response->getBody());
    }

    #[Test]
    public function routineByCallbackReceivesRequestWhenTypeHinted(): void
    {
        $captured = null;
        $this->router->get('/guarded', static function () {
            return 'ok';
        })->by(static function (ServerRequestInterface $req) use (&$captured): void {
            $captured = $req->getHeaderLine('Authorization');
        });

        $serverRequest = (new ServerRequest('GET', '/guarded'))
            ->withHeader('Authorization', 'Bearer token123');
        $this->router->dispatch($serverRequest)->response();

        self::assertEquals('Bearer token123', $captured);
    }

    #[Test]
    public function routineThroughCallbackReceivesResponseWhenTypeHinted(): void
    {
        $this->router->get('/wrapped', static function () {
            return 'content';
        })->through(static function (ResponseInterface $res) {
            return static function ($data) {
                return strtoupper($data);
            };
        });

        $response = $this->router->dispatch(new ServerRequest('GET', '/wrapped'))->response();

        self::assertNotNull($response);
        self::assertEquals('CONTENT', (string) $response->getBody());
    }
}
