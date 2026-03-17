<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Routines\AbstractSyncedRoutine;
use Respect\Rest\Routines\By;
use Respect\Rest\Test\Stubs\ByClassWithInvoke;

/**
 * @covers Respect\Rest\Routines\ParamSynced
 */
#[AllowMockObjectsWithoutExpectations]
final class AbstractSyncedRoutineTest extends TestCase
{
    protected By $object;

    protected function setUp(): void
    {
        $this->object = new By(function ($userId, $blogId) {
              return 'from AbstractSyncedRoutine implementation callback';
            });
    }

    /**
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     */
    public function testGetParameters(): void
    {
        self::assertInstanceOf('Respect\Rest\Routines\ParamSynced', $this->object);
        $parameters = $this->object->getParameters();
        self::assertCount(2, $parameters);
        self::assertEquals('userId', $parameters[0]->name);
        self::assertEquals('blogId', $parameters[1]->name);
        self::assertInstanceOf('ReflectionParameter', $parameters[0]);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     * @covers Respect\Rest\Routines\AbstractRoutine
     */
    public function test_getParameters_with_an_array(): void
    {
        $callback = ['DateTime', 'createFromFormat'];
        $stub = $this->getMockBuilder(AbstractSyncedRoutine::class)
            ->onlyMethods(['getCallback'])
            ->disableOriginalConstructor()
            ->getMock();
        $stub->method('getCallback')->willReturn($callback);

        self::assertContainsOnlyInstancesOf(
            'ReflectionParameter',
            $result = $stub->getParameters()
        );
        self::assertCount(3, $result);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     * @covers Respect\Rest\Routines\AbstractRoutine
     */
    public function test_getParameters_with_function(): void
    {
        $callback = function($name) { return 'Hello '.$name; };
        $stub = $this->getMockBuilder(AbstractSyncedRoutine::class)
            ->onlyMethods(['getCallback'])
            ->disableOriginalConstructor()
            ->getMock();
        $stub->method('getCallback')->willReturn($callback);

        self::assertContainsOnlyInstancesOf(
            'ReflectionParameter',
            $result = $stub->getParameters()
        );
        self::assertCount(1, $result);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     * @covers Respect\Rest\Routines\AbstractRoutine
     */
    public function test_getParameters_with_callable_instance(): void
    {
        $callableInstance = new ByClassWithInvoke();
        self::assertIsCallable($callableInstance, 'Callable instance does not pass the is_callable test.');

        $stub = $this->getMockBuilder(AbstractSyncedRoutine::class)
            ->onlyMethods(['getCallback'])
            ->disableOriginalConstructor()
            ->getMock();
        $stub->method('getCallback')->willReturn($callableInstance);

        self::assertCount(0, $stub->getParameters());
    }
}
