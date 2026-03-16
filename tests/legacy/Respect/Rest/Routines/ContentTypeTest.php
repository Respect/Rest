<?php
namespace Respect\Rest\Routines;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Respect\Rest\Request;
/**
 * @covers Respect\Rest\Routines\ContentType
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class ContentTypeTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var ContentType
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new ContentType([
            'text/html' => function (){return 'from html callback';},
            'application/json' => function (){return 'from json callback';},
        ]);

    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
    }

    /**
     * @covers Respect\Rest\Routines\ContentType::by
     */
    public function testBy()
    {
        $params = [];
        $alias = &$this->object;

        $request = new Request((new ServerRequest('GET', '/'))->withHeader('Content-Type', 'text/html'));
        $this->assertTrue($alias->when($request, $params));
        $this->assertEquals('from html callback', $alias->by($request, $params));

        $request = new Request((new ServerRequest('GET', '/'))->withHeader('Content-Type', 'application/json'));
        $this->assertTrue($alias->when($request, $params));
        $this->assertEquals('from json callback', $alias->by($request, $params));

        $request = new Request((new ServerRequest('GET', '/'))->withHeader('Content-Type', 'text/xml'));
        $this->assertFalse($alias->when($request, $params));
        $this->assertNull($alias->by($request, $params));
    }
}
