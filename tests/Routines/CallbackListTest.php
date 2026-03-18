<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Closure;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Test\Stubs\FunkyCallbackList;

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

    /** @covers Respect\Rest\Routines\CallbackList::executeCallback */
    public function testExecuteCallback(): void
    {
        self::assertEquals('&lt;p&gt;&lt;/p&gt;', $this->object->funkyExecuteCallback('a', ['<p></p>']));
        self::assertTrue($this->object->funkyExecuteCallback('b', []));
        self::assertFalse($this->object->funkyExecuteCallback('c', ['d', 'abc']));
        self::assertTrue($this->object->funkyExecuteCallback('e', [4]));
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

    /** @covers Respect\Rest\Routines\CallbackList::hasKey */
    public function testHasKey(): void
    {
        self::assertTrue($this->object->hasKey('a'));
        self::assertTrue($this->object->hasKey('b'));
        self::assertTrue($this->object->hasKey('c'));
        self::assertFalse($this->object->hasKey('d'));
        self::assertTrue($this->object->hasKey('e'));
    }

    /** @covers Respect\Rest\Routines\CallbackList::filterKeysContain */
    public function testFilterKeysContain(): void
    {
        $a = $this->object->filterKeysContain('b');
        self::assertCount(1, $a);
        self::assertNotContains('a', $a);
        self::assertContains('b', $a);
        self::assertNotContains('c', $a);
        self::assertNotContains('d', $a);
        self::assertNotContains('e', $a);
    }

    /** @covers Respect\Rest\Routines\CallbackList::filterKeysNotContain */
    public function testFilterKeysNotContain(): void
    {
        $a = $this->object->filterKeysNotContain('b');
        self::assertCount(3, $a);
        self::assertContains('a', $a);
        self::assertNotContains('b', $a);
        self::assertContains('c', $a);
        self::assertNotContains('d', $a);
        self::assertContains('e', $a);
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }
}
