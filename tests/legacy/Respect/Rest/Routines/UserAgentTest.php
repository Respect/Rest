<?php
declare(strict_types=1);

namespace Respect\Rest\Routines;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Respect\Rest\Request;
/**
 * @covers Respect\Rest\Routines\UserAgent
 * @author Nick Lombard <github@jigsoft.co.za>
 */
final class UserAgentTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var UserAgent
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new UserAgent([
            'FIREFOX' => function (){},
            'InhernetExplorer' => function (){},
        ]);

    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
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
