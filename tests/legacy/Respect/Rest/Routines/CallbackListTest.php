<?php
declare(strict_types=1);

namespace Respect\Rest\Routines;


/**
 * @covers Respect\Rest\Routines\CallbackList
 * @author Nick Lombard <github@jigsoft.co.za>
 */
final class CallbackListTest extends \PHPUnit\Framework\TestCase
{
    protected $object;

    protected function setUp(): void
    {
        $ar = [
                'a' => 'htmlentities',
                'b' => function () { return true; },
                'c' => 'strpos',
                'd' => 'this is invalid',
                'e' => 'is_numeric',
        ];

        $this->object = new FunkyCallbackList($ar);
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }

    /**
     * @covers Respect\Rest\Routines\CallbackList::executeCallback
     */
    public function testExecuteCallback()
    {
        self::assertEquals('&lt;p&gt;&lt;/p&gt;',$this->object->funkyExecuteCallback('a',['<p></p>']));
        self::assertTrue($this->object->funkyExecuteCallback('b', []));
        self::assertFalse($this->object->funkyExecuteCallback('c', ['d','abc']));
        self::assertTrue($this->object->funkyExecuteCallback('e', [4]));
    }
    /**
     * @covers Respect\Rest\Routines\CallbackList::getCallback
     */
    public function testGetCallback()
    {
        self::assertIsCallable($this->object->funkyGetCallback('a'));
        self::assertIsCallable($this->object->funkyGetCallback('b'));
        self::assertIsCallable($this->object->funkyGetCallback('c'));
        self::assertIsCallable($this->object->funkyGetCallback('e'));
    }
    /**
     * @covers Respect\Rest\Routines\CallbackList::__construct
     */
    public function testLoad()
    {
        $a = $this->object->getKeys();
        self::assertCount(4, $a);
        self::assertContains('a', $a);
        self::assertContains('b', $a);
        self::assertContains('c', $a);
        self::assertNotContains('d', $a, 'Not a callback function');
        self::assertContains('e', $a);
    }
    /**
     * @covers Respect\Rest\Routines\CallbackList::getKeys
     */
    public function testGetKeys()
    {
        $a = $this->object->getKeys();
        self::assertCount(4, $a);
        self::assertContains('a', $a);
        self::assertContains('b', $a);
        self::assertContains('c', $a);
        self::assertNotContains('d', $a, 'Not a callback function');
        self::assertContains('e', $a);
    }

    /**
     * @covers Respect\Rest\Routines\CallbackList::hasKey
     */
    public function testHasKey()
    {
        self::assertTrue($this->object->hasKey('a'));
        self::assertTrue($this->object->hasKey('b'));
        self::assertTrue($this->object->hasKey('c'));
        self::assertFalse($this->object->hasKey('d'));
        self::assertTrue($this->object->hasKey('e'));
    }

    /**
     * @covers Respect\Rest\Routines\CallbackList::filterKeysContain
     */
    public function testFilterKeysContain()
    {
        $a = $this->object->filterKeysContain('b');
        self::assertCount(1, $a);
        self::assertNotContains('a', $a);
        self::assertContains('b', $a);
        self::assertNotContains('c', $a);
        self::assertNotContains('d', $a);
        self::assertNotContains('e', $a);
    }

    /**
     * @covers Respect\Rest\Routines\CallbackList::filterKeysNotContain
     */
    public function testFilterKeysNotContain()
    {
        $a = $this->object->filterKeysNotContain('b');
        self::assertCount(3, $a);
        self::assertContains('a', $a);
        self::assertNotContains('b', $a);
        self::assertContains('c', $a);
        self::assertNotContains('d', $a);
        self::assertContains('e', $a);
    }




}

class FunkyCallbackList extends CallbackList{
    public function funkyExecuteCallback($key, $params) {
        return $this->executeCallback($key, $params);
    }
    public function funkyGetCallback($key) {
        return $this->getCallback($key);
    }
}

