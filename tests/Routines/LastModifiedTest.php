<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Request;
use Respect\Rest\Routines\LastModified;

/**
 * @covers Respect\Rest\Routines\LastModified
 */
final class LastModifiedTest extends TestCase
{
    protected $object;

    protected function setUp(): void
    {
        $this->object = new LastModified(function () {
                return new \DateTime('2011-11-11 11:11:12');
            });
    }

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
        self::assertTrue($alias->by($request, $params));

        // If-Modified-Since is BEFORE lastModified (11:11:11 < 11:11:12) -> returns true (content changed)
        $serverRequest = (new ServerRequest('GET', '/'))->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $request = new Request($serverRequest);
        self::assertTrue($alias->by($request, $params));

        // If-Modified-Since is AFTER lastModified (11:11:13 > 11:11:12) -> returns 304 response
        $serverRequest = (new ServerRequest('GET', '/'))->withHeader('If-Modified-Since', '2011-11-11 11:11:13');
        $request = new Request($serverRequest);
        $request->route = $this->createRouteWithResponseFactory();
        $response = $alias->by($request, $params);
        self::assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
        self::assertEquals(304, $response->getStatusCode());
        self::assertEquals('Fri, 11 Nov 2011 11:11:12 +0000', $response->getHeaderLine('Last-Modified'));
    }

    private function createRouteWithResponseFactory()
    {
        $route = $this->createStub(\Respect\Rest\Routes\AbstractRoute::class);
        $route->responseFactory = new Psr17Factory();
        return $route;
    }
}
