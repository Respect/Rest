<?php
namespace Respect\Rest\Routines;

use Respect\Rest\Request;
/**
 * @covers Respect\Rest\Routines\ContentType
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class ContentTypeTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ContentType
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new ContentType(array(
            'text/html' => function (){return 'from html callback';},
            'application/json' => function (){return 'from json callback';},
        ));

    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    /**
     * @covers Respect\Rest\Routines\ContentType::by
     */
    public function testBy()
    {
        $request = @new Request();
        $params = array();
        $alias = &$this->object;

        $_SERVER['CONTENT_TYPE'] = 'text/html';
        $this->assertTrue($alias->when($request, $params));
        $this->assertEquals('from html callback', $alias->by($request, $params));

        $_SERVER['CONTENT_TYPE'] = 'application/json';
        $this->assertTrue($alias->when($request, $params));
        $this->assertEquals('from json callback', $alias->by($request, $params));

        $_SERVER['CONTENT_TYPE'] = 'text/xml';
        $this->assertFalse($alias->when($request, $params));
        $this->assertNull($alias->by($request, $params));
    }
}
