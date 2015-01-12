<?php

namespace Respect\Rest\Routines;

use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;
use Stubs\Routines\AbstractRoutine as Stub;
use Stubs\Routines\WhenAlwaysTrue as InstanceWithInvoke;

/** Test an AbstractRoutine (abstract class) instantiation through a stub class. */
class AbstractRoutineTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider provide_valid_constructor_arguments
     * @covers Respect\Rest\Routines\AbstractRoutine
     */
    public function test_valid_constructor_arguments($argument)
    {
        $this->assertInstanceOf(
            'Respect\Rest\Routines\AbstractRoutine',
            $result = new Stub($argument)
        );
        $this->assertSame(
            $expected = $argument,
            $result   = $result->getCallback()
        );
    }

    public function provide_valid_constructor_arguments()
    {
        return array(
            array(function() { return 'Hello'; }), // an anonymous function
            array(array('DateTime', 'createFromFormat')), // a class-method callable pair
            array(new InstanceWithInvoke), // instance of a callable class
            array('Stubs\Routines\WhenAlwaysTrue') // a callable class name
        );
    }

    /**
     * @dataProvider provide_invalid_constructor_arguments
     * @covers Respect\Rest\Routines\AbstractRoutine
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Routine callback must be... guess what... callable!
     */
    public function test_invalid_constructor_arguments($argument)
    {
        $result = new Stub($argument);
    }

    public function provide_invalid_constructor_arguments()
    {
        return array(
            array('this_function_name_does_not_exist'),
            array(new \StdClass), // an instance that is not callable
        );
    }
}
