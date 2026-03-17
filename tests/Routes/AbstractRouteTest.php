<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Router;
use Respect\Rest\Routes\Factory;

/**
 * @covers Respect\Rest\Routes\AbstractRoute
 */
final class AbstractRouteTest extends TestCase
{
    public static function extensions_provider()
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

    /**
     * @covers Respect\Rest\Routes\AbstractRoute::match
     */
    #[DataProvider('extensions_provider')]
    public function testIgnoreFileExtensions($with, $without)
    {
        $r = new Router(new Psr17Factory());
        $r->get('/route1/*', function ($match) {return $match;});
        $r->get('/route2/*', function ($match) {return $match;})
            ->accept([
            '.json-home' => function ($data) {
                return Factory::respond('.json-home', $data);
            },
            "*" => function($data){ return "$data.accepted";},
        ]);

        $serverRequest1 = (new ServerRequest('get', "/route1/$with"))->withHeader('Accept', '*');
        $response = (string) $r->dispatch($serverRequest1)->response()->getBody();
        self::assertEquals($with, $response);
        $serverRequest2 = (new ServerRequest('get', "/route2/$with"))->withHeader('Accept', '*');
        $response = (string) $r->dispatch($serverRequest2)->response()->getBody();
        self::assertEquals("$without.accepted", $response);
    }
}
