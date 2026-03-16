<?php
namespace Respect\Rest\Routines;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Respect\Rest\Request;
/**
 * @covers Respect\Rest\Routines\UserAgent
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class UserAgentTest extends \PHPUnit\Framework\TestCase
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
        $this->assertTrue($alias->when($request, $params));
        $this->assertInstanceOf('Closure', $alias->through($request, $params));

        $request = new Request((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'InhernetExplorer'));
        $this->assertTrue($alias->when($request, $params));
        $this->assertInstanceOf('Closure', $alias->through($request, $params));

    }
    public function testThroughInvalid()
    {
        $params = [];
        $alias = &$this->object;
        $request = new Request((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'CHROME'));
        $this->assertInstanceOf('Respect\\Rest\\Routines\\UserAgent', $alias);
        $this->assertFalse($alias->when($request, $params));
        $this->assertNull($alias->through($request, $params));
    }

}
