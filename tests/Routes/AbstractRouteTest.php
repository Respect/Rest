<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Responder;
use Respect\Rest\Router;

/** @covers Respect\Rest\Routes\AbstractRoute */
final class AbstractRouteTest extends TestCase
{
    /** @return array<int, array<int, string>> */
    public static function extensions_provider(): array
    {
        return [
            ['.json',             'test.json',             'test'],
            ['.bz2',              'test.bz2',              'test'],
            ['.html',             'test.en.html',          'test.en'],
            ['.ext',              'test.my_funny.ext',     'test.my_funny'],
        ];
    }

    /** @covers Respect\Rest\Routes\AbstractRoute::match */
    #[DataProvider('extensions_provider')]
    public function testIgnoreFileExtensions(string $ext, string $with, string $without): void
    {
        $r = new Router('', new Psr17Factory());

        // Route without FileExtension: dots preserved in params
        $r->get('/route1/*', static function ($match) {
            return $match;
        });

        // Route with FileExtension: declared extension stripped
        $r->get('/route2/*', static function ($match) {
            return $match;
        })->fileExtension([
            $ext => static function ($data) {
                return $data . '.transformed';
            },
        ]);

        // route1: extension NOT stripped (no IgnorableFileExtension routine)
        $resp1 = $r->dispatch(new ServerRequest('get', '/route1/' . $with))->response();
        self::assertNotNull($resp1);
        self::assertEquals($with, (string) $resp1->getBody());

        // route2: declared extension stripped, callback applied
        $resp2 = $r->dispatch(new ServerRequest('get', '/route2/' . $with))->response();
        self::assertNotNull($resp2);
        self::assertEquals($without . '.transformed', (string) $resp2->getBody());
    }

    public function testWrapResponseNormalizesArrayResults(): void
    {
        $factory = new Psr17Factory();
        $response = (new Responder($factory))
            ->normalize(['status' => 'ok']);

        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"status":"ok"}', (string) $response->getBody());
    }
}
