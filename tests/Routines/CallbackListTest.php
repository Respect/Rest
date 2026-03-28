<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Closure;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Test\Stubs\FunkyCallbackList;
use UnexpectedValueException;

/** @covers Respect\Rest\Routines\CallbackList */
final class CallbackListTest extends TestCase
{
    protected FunkyCallbackList $object;

    protected function setUp(): void
    {
        $ar = [
            'a' => 'htmlentities',
            'b' => static function () {
                return true;
            },
            'c' => 'strpos',
            'd' => 'this is invalid',
            'e' => 'is_numeric',
        ];

        /** @phpstan-ignore-next-line intentionally passing non-callable to test filtering */
        $this->object = new FunkyCallbackList($ar);
    }

    /** @covers Respect\Rest\Routines\CallbackList::getCallback */
    public function testGetCallback(): void
    {
        self::assertSame('htmlentities', $this->object->funkyGetCallback('a'));
        self::assertInstanceOf(Closure::class, $this->object->funkyGetCallback('b'));
        self::assertSame('strpos', $this->object->funkyGetCallback('c'));
        self::assertSame('is_numeric', $this->object->funkyGetCallback('e'));
    }

    /** @covers Respect\Rest\Routines\CallbackList::__construct */
    public function testLoad(): void
    {
        $a = $this->object->getKeys();
        self::assertCount(4, $a);
        self::assertContains('a', $a);
        self::assertContains('b', $a);
        self::assertContains('c', $a);
        self::assertNotContains('d', $a, 'Not a callback function');
        self::assertContains('e', $a);
    }

    /** @covers Respect\Rest\Routines\CallbackList::getKeys */
    public function testGetKeys(): void
    {
        $a = $this->object->getKeys();
        self::assertCount(4, $a);
        self::assertContains('a', $a);
        self::assertContains('b', $a);
        self::assertContains('c', $a);
        self::assertNotContains('d', $a, 'Not a callback function');
        self::assertContains('e', $a);
    }

    public function testConstructorThrowsWhenNoCallablesProvided(): void
    {
        $this->expectException(UnexpectedValueException::class);
        /** @phpstan-ignore argument.type */
        new FunkyCallbackList(['not_a_callable_string_xyz']);
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }
}
