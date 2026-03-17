<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Router;
use Respect\Rest\Routes\Callback;
use Respect\Rest\Routes\Factory;

/** @covers Respect\Rest\Routes\AbstractRoute */
final class AbstractRouteTest extends TestCase
{
    /** @return array<int, array<int, string>> */
    public static function extensions_provider(): array
    {
        return [
            ['test.json',             'test'],
            ['test.bz2',              'test'],
            ['test.json~user',        'test'],
            ['test.hal+json',         'test'],
            ['test.en.html',          'test'],
            ['test.vnd.amazon.ebook', 'test'],
            ['test.vnd.hp-hps',       'test'],
            ['test.json-patch',       'test'],
            ['test.my_funny.ext',     'test'],
        ];
    }

    /** @covers Respect\Rest\Routes\AbstractRoute::match */
    #[DataProvider('extensions_provider')]
    public function testIgnoreFileExtensions(string $with, string $without): void
    {
        $r = new Router(new Psr17Factory());
        $r->get('/route1/*', static function ($match) {
            return $match;
        });
        $r->get('/route2/*', static function ($match) {
            return $match;
        })
            ->accept([
                '.json-home' => static function ($data) {
                    /** @phpstan-ignore-next-line */
                    return Factory::respond('.json-home', $data);
                },
                '*' => static function ($data) {
                    return $data . '.accepted';
                },
            ]);

        $serverRequest1 = (new ServerRequest('get', '/route1/' . $with))->withHeader('Accept', '*');
        $resp1 = $r->dispatch($serverRequest1)->response();
        self::assertNotNull($resp1);
        $response = (string) $resp1->getBody();
        self::assertEquals($with, $response);
        $serverRequest2 = (new ServerRequest('get', '/route2/' . $with))->withHeader('Accept', '*');
        $resp2 = $r->dispatch($serverRequest2)->response();
        self::assertNotNull($resp2);
        $response = (string) $resp2->getBody();
        self::assertEquals($without . '.accepted', $response);
    }

    public function testWrapResponseNormalizesArrayResults(): void
    {
        $route = new Callback('GET', '/', static fn() => null);
        $route->responseFactory = new Psr17Factory();

        $response = $route->wrapResponse(['status' => 'ok']);

        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"status":"ok"}', (string) $response->getBody());
    }
}
