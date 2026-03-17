<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Test\Stubs\AbstractRoutine as Stub;
use Respect\Rest\Test\Stubs\WhenAlwaysTrue as InstanceWithInvoke;

final class AbstractRoutineTest extends TestCase
{
    #[DataProvider('provide_valid_constructor_arguments')]
    public function test_valid_constructor_arguments($argument)
    {
        self::assertInstanceOf(
            'Respect\Rest\Routines\AbstractRoutine',
            $result = new Stub($argument)
        );
        self::assertSame(
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
            ['Respect\Rest\Test\Stubs\WhenAlwaysTrue']
        ];
    }

    #[DataProvider('provide_invalid_constructor_arguments')]
    public function test_invalid_constructor_arguments($argument)
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Routine callback must be... guess what... callable!');
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
