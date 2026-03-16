<?php
namespace Respect\Rest\Routes {

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use Respect\Rest\Router;
/**
 * @covers Respect\Rest\Routes\AbstractRoute
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class AbstractRouteTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var AbstractRoute local instance
     */
    protected $object;

    /**
     * Setting the stage for our unit tests.
     */
    protected function setUp(): void
    {
    }

    /**
     * This is where we would clean up after testing, if necessary.
     */
    protected function tearDown(): void
    {
    }


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
        $_SERVER['HTTP_ACCEPT'] = '*';
        $_SERVER['REQUEST_URI'] = '/';
        $r = new Router(new Psr17Factory());
        $r->get('/route1/*', function ($match) {return $match;});
        $r->get('/route2/*', function ($match) {return $match;})
            ->accept([
            '.json-home' => function ($data) {
                return factory::respond(E2M::mediaType('.json-home'), $data);
            },
            "*" => function($data){ return "$data.accepted";},

        ]);

        $response = (string) $r->dispatch(new ServerRequest('get', "/route1/$with"))->response()->getBody();
        $this->assertEquals($with, $response);
        $response = (string) $r->dispatch(new ServerRequest('get', "/route2/$with"))->response()->getBody();
        $this->assertEquals("$without.accepted", $response);
    }

}

/** Environment shims to prevent header errors */
    if (!function_exists(__NAMESPACE__.'\\header')) {
        function header($string, $replace=true, $http_response_code=200)
        {
            global $header;
            if (!$replace && isset($header))
                return;

            $header[$string] = $string;
        }
    }
}

namespace Respect\Rest\Routines {
    if (!function_exists(__NAMESPACE__.'\\header')) {
        function header($string, $replace=true, $http_response_code=200)
        {
            global $header;
            if (!$replace && isset($header))
                return;

            $header[$string] = $string;
        }
    }
}
namespace Respect\Rest {
    if (!function_exists(__NAMESPACE__.'\\header')) {
        function header($string, $replace=true, $http_response_code=200)
        {
            global $header;
            if (!$replace && isset($header))
                return;

            $header[$string] = $string;
        }
    }
}

namespace {
    $header=[];
}
