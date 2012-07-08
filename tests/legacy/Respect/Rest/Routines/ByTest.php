<?php
namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/**
 * @covers Respect\Rest\Routines\By
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class ByTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var By
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new By(function () {
              return 'from by callback';
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
     * @covers Respect\Rest\Routines\By::by
     */
    public function testBy()
    {
        $request = @new Request();
        $params = array();
        $alias = &$this->object;
        $this->assertEquals('from by callback',
                $alias->by($request, $params));
    }
}
