<?php
namespace Respect\Rest\Routes {

use Respect\Rest\Router;
/**
 * @covers Respect\Rest\Routes\AbstractRoute
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class AbstractRouteTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AbstractRoute local instance
     */
    protected $object;

    /**
     * Setting the stage for our unit tests.
     */
    protected function setUp()
    {
    }

    /**
     * This is where we would clean up after testing, if necessary.
     */
    protected function tearDown()
    {
    }


    function extensions_provider()
    {
        return array(
            array('test.json',             'test'),
            array('test.bz2',              'test'),
            array('test.json~user',        'test'),
            array('test.hal+json',         'test'),
            array('test.en.html',          'test'),
            array('test.vnd.amazon.ebook', 'test'),
            array('test.vnd.hp-hps',       'test'),
            array('test.json-patch',       'test'),
            array('test.my_funny.ext',     'test'),
        );
    }
    /**
     * @covers Respect\Rest\Routes\AbstractRoute::match
     * @dataProvider extensions_provider
     */
    public function testIgnoreFileExtensions($with, $without)
    {
        $_SERVER['HTTP_ACCEPT'] = '*';
        $_SERVER['REQUEST_URI'] = '/';
        $r = new Router();
        $r->get('/route1/*', function ($match) {return $match;});
        $r->get('/route2/*', function ($match) {return $match;})
            ->accept(array(
            '.json-home' => function ($data) {
                return factory::respond(E2M::mediaType('.json-home'), $data);
            },
            "*" => function($data){ return "$data.accepted";},

        ));

        $response = $r->dispatch('get', "/route1/$with")->response();
        $this->assertEquals($with, $response);
        $response = $r->dispatch('get', "/route2/$with")->response();
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
    $header=array();
}
