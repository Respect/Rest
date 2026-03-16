<?php

namespace Respect\Rest\Routines;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Stubs\Routines\AbstractRoutine as Stub;
use Stubs\Routines\WhenAlwaysTrue as InstanceWithInvoke;

class AbstractRoutineTest extends \PHPUnit\Framework\TestCase
{
    #[DataProvider('provide_valid_constructor_arguments')]
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

    public static function provide_valid_constructor_arguments()
    {
        return [
            [function() { return 'Hello'; }],
            [['DateTime', 'createFromFormat']],
            [new InstanceWithInvoke],
            ['Stubs\Routines\WhenAlwaysTrue']
        ];
    }

    #[DataProvider('provide_invalid_constructor_arguments')]
    public function test_invalid_constructor_arguments($argument)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Routine callback must be... guess what... callable!');
        $result = new Stub($argument);
    }

    public static function provide_invalid_constructor_arguments()
    {
        return [
            ['this_function_name_does_not_exist'],
            [new \StdClass],
        ];
    }
}
