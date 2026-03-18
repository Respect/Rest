<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routines\UserAgent;

/** @covers Respect\Rest\Routines\UserAgent */
final class UserAgentTest extends TestCase
{
    protected UserAgent $object;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
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

        $context = new DispatchContext(
            (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'),
            $this->factory,
        );
        self::assertTrue($alias->when($context, $params));
        self::assertInstanceOf('Closure', $alias->through($context, $params));

        $context = new DispatchContext(
            (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'InhernetExplorer'),
            $this->factory,
        );
        self::assertTrue($alias->when($context, $params));
        self::assertInstanceOf('Closure', $alias->through($context, $params));
    }

    public function testThroughInvalid(): void
    {
        $params = [];
        $alias = &$this->object;
        $context = new DispatchContext(
            (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'CHROME'),
            $this->factory,
        );
        self::assertInstanceOf('Respect\\Rest\\Routines\\UserAgent', $alias);
        self::assertFalse($alias->when($context, $params));
        self::assertNull($alias->through($context, $params));
    }

    public function testByCanDenyBeforeRoute(): void
    {
        $routine = new UserAgent([
            'FIREFOX' => static function (): bool {
                return false;
            },
        ]);
        $context = new DispatchContext(
            (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'),
            $this->factory,
        );

        self::assertTrue($routine->when($context, []));
        self::assertFalse($routine->by($context, []));
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
        $context = new DispatchContext(
            (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'),
            $this->factory,
        );

        self::assertTrue($routine->when($context, []));
        self::assertNull($routine->by($context, []));

        $through = $routine->through($context, []);
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
        $context = new DispatchContext(
            (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'),
            $this->factory,
        );

        self::assertTrue($routine->when($context, []));
        self::assertNull($routine->by($context, []));
        self::assertNull($routine->through($context, []));
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }
}
