<?php
namespace Respect\Rest\Routines;

use Respect\Rest\Request;
/**
 * @covers Respect\Rest\Routines\UserAgent
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class UserAgentTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UserAgent
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new UserAgent(array(
            'FIREFOX' => function (){},
            'InhernetExplorer' => function (){},
        ));

    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        unset($this->object);
    }


    public function testThrough()
    {
        $request = @new Request();
        $params = array();
        $alias = &$this->object;

        $_SERVER['HTTP_USER_AGENT'] = 'FIREFOX';
        $this->assertTrue($alias->when($request, $params));
        $this->assertInstanceOf('Closure', $alias->through($request, $params));

        $_SERVER['HTTP_USER_AGENT'] = 'InhernetExplorer';
        $this->assertTrue($alias->when($request, $params));
        $this->assertInstanceOf('Closure', $alias->through($request, $params));

    }
    public function testThroughInvalid()
    {
        $request = @new Request();
        $params = array();
        $alias = &$this->object;
        $_SERVER['HTTP_USER_AGENT'] = 'CHROME';
        $this->assertInstanceOf('Respect\\Rest\\Routines\\UserAgent', $alias);
        $this->assertFalse($alias->when($request, $params));
        $this->assertNull($alias->through($request, $params));
    }

}
