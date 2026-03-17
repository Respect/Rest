<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Request;
use Respect\Rest\Routines\ContentType;

/** @covers Respect\Rest\Routines\ContentType */
final class ContentTypeTest extends TestCase
{
    protected ContentType $object;

    protected function setUp(): void
    {
        $this->object = new ContentType([
            'text/html' => static function () {
                return 'from html callback';
            },
            'application/json' => static function () {
                return 'from json callback';
            },
        ]);
    }

    /** @covers Respect\Rest\Routines\ContentType::by */
    public function testBy(): void
    {
        $params = [];
        $alias = &$this->object;

        $request = new Request((new ServerRequest('GET', '/'))->withHeader('Content-Type', 'text/html'));
        self::assertTrue($alias->when($request, $params));
        self::assertEquals('from html callback', $alias->by($request, $params));

        $request = new Request((new ServerRequest('GET', '/'))->withHeader('Content-Type', 'application/json'));
        self::assertTrue($alias->when($request, $params));
        self::assertEquals('from json callback', $alias->by($request, $params));

        $request = new Request((new ServerRequest('GET', '/'))->withHeader('Content-Type', 'text/xml'));
        self::assertFalse($alias->when($request, $params));
        self::assertNull($alias->by($request, $params));
    }
}
