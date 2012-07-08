<?php
namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/**
 * @covers Respect\Rest\Routines\Through
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class ThroughTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Through
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new Through(function () {
            return 'from through callback';
            });
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        unset($this->object);
    }

    /**
     * @covers Respect\Rest\Routines\Through::through
     */
    public function testThrough()
    {
        $request = @new Request();
        $params = array();
        $alias = &$this->object;
        $this->assertEquals('from through callback',
                $alias->through($request, $params));
    }
}
