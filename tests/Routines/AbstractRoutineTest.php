<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Test\Stubs\AbstractRoutine as Stub;
use Respect\Rest\Test\Stubs\WhenAlwaysTrue as InstanceWithInvoke;
use TypeError;

final class AbstractRoutineTest extends TestCase
{
    #[DataProvider('provide_valid_constructor_arguments')]
    public function test_valid_constructor_arguments(callable $argument): void
    {
        self::assertInstanceOf(
            'Respect\Rest\Routines\AbstractRoutine',
            $result = new Stub($argument),
        );
        self::assertSame(
            $expected = $argument,
            $result   = $result->getCallback(),
        );
    }

    /** @return array<int, array<int, callable>> */
    public static function provide_valid_constructor_arguments(): array
    {
        return [
            [
                static function () {
                    return 'Hello';
                },
            ],
            [['DateTime', 'createFromFormat']],
            [new InstanceWithInvoke()],
        ];
    }

    public function test_invalid_constructor_arguments(): void
    {
        self::expectException(TypeError::class);
        new Stub('this_function_name_does_not_exist'); // @phpstan-ignore argument.type
    }
}
