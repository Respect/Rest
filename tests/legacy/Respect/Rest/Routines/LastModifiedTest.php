<?php
namespace Respect\Rest\Routines {

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Respect\Rest\Request;
/**
 * @covers Respect\Rest\Routines\LastModified
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class LastModifiedTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var LastModified
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        global $header;
        $header = [];
        unset($_SERVER['IF_MODIFIED_SINCE']);
        $this->object = new LastModified(function () {
                return new \DateTime('2011-11-11 11:11:12');

            });
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
        unset($this->object);
    }

    /**
     * @covers Respect\Rest\Routines\LastModified::by
     */
    public function testBy()
    {
        $alias = &$this->object;
        $params = [];

        // No If-Modified-Since header -> returns true
        $request = new Request(new ServerRequest('GET', '/'));
        $this->assertTrue($alias->by($request, $params));

        // If-Modified-Since is BEFORE lastModified (11:11:11 < 11:11:12) -> returns true (content changed)
        $serverRequest = (new ServerRequest('GET', '/'))->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $request = new Request($serverRequest);
        // Need to set route with responseFactory for 304 path
        $this->assertTrue($alias->by($request, $params));

        // If-Modified-Since is AFTER lastModified (11:11:13 > 11:11:12) -> returns 304 response
        $serverRequest = (new ServerRequest('GET', '/'))->withHeader('If-Modified-Since', '2011-11-11 11:11:13');
        $request = new Request($serverRequest);
        $request->route = $this->createRouteWithResponseFactory();
        $response = $alias->by($request, $params);
        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
        $this->assertEquals(304, $response->getStatusCode());
        $this->assertEquals('Fri, 11 Nov 2011 11:11:12 +0000', $response->getHeaderLine('Last-Modified'));
    }

    private function createRouteWithResponseFactory()
    {
        $route = $this->getMockBuilder(\Respect\Rest\Routes\AbstractRoute::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getReflection', 'runTarget'])
            ->getMock();
        $route->responseFactory = new Psr17Factory();
        return $route;
    }
}


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
