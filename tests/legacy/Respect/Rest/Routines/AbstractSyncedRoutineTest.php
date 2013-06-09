<?php
namespace Respect\Rest\Routines;

use Stubs\Routines\ByClassWithInvoke;

/**
 * @covers Respect\Rest\Routines\ParamSynced
 * @author Nick Lombard <github@jigsoft.co.za>
 */
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
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
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

    /**
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     * @covers Respect\Rest\Routines\AbstractRoutine
     */
    public function test_getParameters_with_an_array()
    {
        $class    = 'Respect\Rest\Routines\AbstractSyncedRoutine';
        $callback = array('DateTime', 'createFromFormat');
        $stub     = $this->getMockBuilder($class)
                         ->setMethods(array('getCallback'))
                         ->disableOriginalConstructor()
                         ->getMock();
        $stub->expects($this->any())
             ->method('getCallback')
             ->will($this->returnValue($callback));

        $this->assertContainsOnlyInstancesOf(
            $expected = 'ReflectionParameter',
            $result   = $stub->getParameters()
        );
        $this->assertCount(
            $expected = 3,
            $result
        );
    }

    /**
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     * @covers Respect\Rest\Routines\AbstractRoutine
     */
    public function test_getParameters_with_function()
    {
        $class    = 'Respect\Rest\Routines\AbstractSyncedRoutine';
        $callback = function($name) { return 'Hello '.$name; };
        $stub     = $this->getMockBuilder($class)
                         ->setMethods(array('getCallback'))
                         ->disableOriginalConstructor()
                         ->getMock();
        $stub->expects($this->any())
             ->method('getCallback')
             ->will($this->returnValue($callback));

        $this->assertContainsOnlyInstancesOf(
            $expected = 'ReflectionParameter',
            $result   = $stub->getParameters()
        );
        $this->assertCount(
            $expected = 1,
            $result
        );
    }

    /**
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     * @covers Respect\Rest\Routines\AbstractRoutine
     */
    public function  test_getParameters_with_callable_instance()
    {
        $stub     = new ByClassWithInvoke;
        $this->assertTrue(
            is_callable($stub),
            'Callable instance does not pass the is_callable test.'
        );
        $class    = 'Respect\Rest\Routines\AbstractSyncedRoutine';
        $callback = function($name) { return 'Hello '.$name; };
        $routine  = $this->getMockBuilder($class)
                         ->setMethods(array('getCallback'))
                         ->disableOriginalConstructor()
                         ->getMock();
        $routine->expects($this->any())
                ->method('getCallback')
                ->will($this->returnValue($stub));
        $this->assertCount(
            $expected = 0,
            $result   = $routine->getParameters()
        );
    }
}
