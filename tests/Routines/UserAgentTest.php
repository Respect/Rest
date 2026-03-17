<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Request;
use Respect\Rest\Routines\UserAgent;

/** @covers Respect\Rest\Routines\UserAgent */
final class UserAgentTest extends TestCase
{
    protected UserAgent $object;

    protected function setUp(): void
    {
        $this->object = new UserAgent([
            'FIREFOX' => static function (): void {
            },
            'InhernetExplorer' => static function (): void {
            },
        ]);
    }

    public function testThrough(): void
    {
        $params = [];
        $alias = &$this->object;

        $request = new Request((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'));
        self::assertTrue($alias->when($request, $params));
        self::assertInstanceOf('Closure', $alias->through($request, $params));

        $request = new Request((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'InhernetExplorer'));
        self::assertTrue($alias->when($request, $params));
        self::assertInstanceOf('Closure', $alias->through($request, $params));
    }

    public function testThroughInvalid(): void
    {
        $params = [];
        $alias = &$this->object;
        $request = new Request((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'CHROME'));
        self::assertInstanceOf('Respect\\Rest\\Routines\\UserAgent', $alias);
        self::assertFalse($alias->when($request, $params));
        self::assertNull($alias->through($request, $params));
    }

    public function testByCanDenyBeforeRoute(): void
    {
        $routine = new UserAgent([
            'FIREFOX' => static function (): bool {
                return false;
            },
        ]);
        $request = new Request((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'));

        self::assertTrue($routine->when($request, []));
        self::assertFalse($routine->by($request, []));
    }

    public function testByCanPrepareThroughResultWithoutRunningTwice(): void
    {
        $calls = 0;
        $routine = new UserAgent([
            'FIREFOX' => static function () use (&$calls) {
                $calls++;

                return static function (string $output): string {
                    return $output . '-FIREFOX';
                };
            },
        ]);
        $request = new Request((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'));

        self::assertTrue($routine->when($request, []));
        self::assertNull($routine->by($request, []));

        $through = $routine->through($request, []);
        self::assertIsCallable($through);
        self::assertSame('ok-FIREFOX', $through('ok'));
        self::assertSame(1, $calls);
    }

    public function testByCanSkipThroughWhenPreRouteResultIsEmpty(): void
    {
        $routine = new UserAgent([
            'FIREFOX' => static function (): bool {
                return true;
            },
        ]);
        $request = new Request((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'));

        self::assertTrue($routine->when($request, []));
        self::assertNull($routine->by($request, []));
        self::assertNull($routine->through($request, []));
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }
}
