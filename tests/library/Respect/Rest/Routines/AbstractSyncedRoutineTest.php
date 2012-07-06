<?php
namespace Respect\Rest\Routines;

/**
 * @covers Respect\Rest\Routines\ParamSynced
 * @author Nick Lombard <github@jigsoft.co.za>
 */
use \ReflectionParameterr;

class AbstractSyncedRoutineTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AbstractSyncedRoutine
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine::getReflection
     */
    protected function setUp()
    {
        $this->object = new By(function ($userId, $blogId) {
              return 'from AbstractSyncedRoutine implementation callback';
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
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine::getParameters
     */
    public function testGetParameters()
    {
        $this->assertInstanceOf('Respect\Rest\Routines\ParamSynced',$this->object);
        $parameters = $this->object->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertEquals('userId', $parameters[0]->name);
        $this->assertEquals('blogId', $parameters[1]->name);
        $this->assertInstanceOf('ReflectionParameter', $parameters[0]);
    }
}
