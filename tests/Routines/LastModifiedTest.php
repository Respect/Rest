<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use DateTime;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\LastModified;

/** @covers Respect\Rest\Routines\LastModified */
final class LastModifiedTest extends TestCase
{
    protected LastModified $object;

    protected function setUp(): void
    {
        $this->object = new LastModified(static function () {
                return new DateTime('2011-11-11 11:11:12');
        });
    }

    /** @covers Respect\Rest\Routines\LastModified::by */
    public function testBy(): void
    {
        $alias = &$this->object;
        $params = [];

        // No If-Modified-Since header -> returns true
        $context = new DispatchContext(new ServerRequest('GET', '/'));
        self::assertTrue($alias->by($context, $params));

        // If-Modified-Since is BEFORE lastModified (11:11:11 < 11:11:12) -> returns true (content changed)
        $serverRequest = (new ServerRequest('GET', '/'))->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $context = new DispatchContext($serverRequest);
        self::assertTrue($alias->by($context, $params));

        // If-Modified-Since is AFTER lastModified (11:11:13 > 11:11:12) -> returns 304 response
        $serverRequest = (new ServerRequest('GET', '/'))->withHeader('If-Modified-Since', '2011-11-11 11:11:13');
        $context = new DispatchContext($serverRequest);
        $context->route = $this->createRouteWithResponseFactory();
        $response = $alias->by($context, $params);
        self::assertInstanceOf(ResponseInterface::class, $response);
        self::assertEquals(304, $response->getStatusCode());
        self::assertEquals('Fri, 11 Nov 2011 11:11:12 +0000', $response->getHeaderLine('Last-Modified'));
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }

    private function createRouteWithResponseFactory(): AbstractRoute
    {
        $route = $this->createStub(AbstractRoute::class);
        $route->responseFactory = new Psr17Factory();

        return $route;
    }
}
