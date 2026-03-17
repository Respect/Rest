<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Request;
use Respect\Rest\Routines\UserAgent;

/**
 * @covers Respect\Rest\Routines\UserAgent
 */
final class UserAgentTest extends TestCase
{
    protected $object;

    protected function setUp(): void
    {
        $this->object = new UserAgent([
            'FIREFOX' => function (){},
            'InhernetExplorer' => function (){},
        ]);
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }

    public function testThrough()
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

    public function testThroughInvalid()
    {
        $params = [];
        $alias = &$this->object;
        $request = new Request((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'CHROME'));
        self::assertInstanceOf('Respect\\Rest\\Routines\\UserAgent', $alias);
        self::assertFalse($alias->when($request, $params));
        self::assertNull($alias->through($request, $params));
    }
}
