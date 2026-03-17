<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Request;
use Respect\Rest\Routines\ContentType;

use function json_decode;
use function strtoupper;

/** @covers Respect\Rest\Routines\ContentType */
final class ContentTypeTest extends TestCase
{
    protected ContentType $object;

    protected function setUp(): void
    {
        $this->object = new ContentType([
            'text/html' => static function (string $input) {
                return strtoupper($input);
            },
            'application/json' => static function (string $input): array {
                return json_decode($input, true);
            },
        ]);
    }

    /** @covers Respect\Rest\Routines\ContentType::by */
    public function testBy(): void
    {
        $params = [];
        $alias = &$this->object;
        $factory = new Psr17Factory();

        $request = new Request(
            (new ServerRequest('GET', '/'))
                ->withHeader('Content-Type', 'text/html')
                ->withBody($factory->createStream('from html callback')),
        );
        self::assertTrue($alias->when($request, $params));
        self::assertNull($alias->by($request, $params));
        self::assertEquals('FROM HTML CALLBACK', $request->serverRequest->getAttribute(ContentType::ATTRIBUTE));

        $request = new Request(
            (new ServerRequest('GET', '/'))
                ->withHeader('Content-Type', 'application/json')
                ->withBody($factory->createStream('{"source":"json"}')),
        );
        self::assertTrue($alias->when($request, $params));
        self::assertNull($alias->by($request, $params));
        self::assertEquals(['source' => 'json'], $request->serverRequest->getParsedBody());
        self::assertEquals(['source' => 'json'], $request->serverRequest->getAttribute(ContentType::ATTRIBUTE));

        $request = new Request((new ServerRequest('GET', '/'))->withHeader('Content-Type', 'text/xml'));
        self::assertFalse($alias->when($request, $params));
        self::assertNull($alias->by($request, $params));
        self::assertSame(415, $request->responseStatus);
    }

    public function testWhenAllowsMissingContentTypeHeader(): void
    {
        $params = [];

        $request = new Request(new ServerRequest('GET', '/'));

        self::assertTrue($this->object->when($request, $params));
        self::assertNull($this->object->by($request, $params));
        self::assertNull($request->responseStatus);
    }
}
