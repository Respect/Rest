<?php
namespace Respect\Rest\Routines;


/**
 * @covers Respect\Rest\Routines\CallbackList
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class CallbackListTest extends \PHPUnit\Framework\TestCase
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
        $this->assertEquals('&lt;p&gt;&lt;/p&gt;',$this->object->funkyExecuteCallback('a',['<p></p>']));
        $this->assertTrue($this->object->funkyExecuteCallback('b', []));
        $this->assertFalse($this->object->funkyExecuteCallback('c', ['d','abc']));
        $this->assertTrue($this->object->funkyExecuteCallback('e', [4]));
    }
    /**
     * @covers Respect\Rest\Routines\CallbackList::getCallback
     */
    public function testGetCallback()
    {
        $this->assertIsCallable($this->object->funkyGetCallback('a'));
        $this->assertIsCallable($this->object->funkyGetCallback('b'));
        $this->assertIsCallable($this->object->funkyGetCallback('c'));
        $this->assertIsCallable($this->object->funkyGetCallback('e'));
    }
    /**
     * @covers Respect\Rest\Routines\CallbackList::__construct
     */
    public function testLoad()
    {
        $a = $this->object->getKeys();
        $this->assertCount(4, $a);
        $this->assertContains('a', $a);
        $this->assertContains('b', $a);
        $this->assertContains('c', $a);
        $this->assertNotContains('d', $a, 'Not a callback function');
        $this->assertContains('e', $a);
    }
    /**
     * @covers Respect\Rest\Routines\CallbackList::getKeys
     */
    public function testGetKeys()
    {
        $a = $this->object->getKeys();
        $this->assertCount(4, $a);
        $this->assertContains('a', $a);
        $this->assertContains('b', $a);
        $this->assertContains('c', $a);
        $this->assertNotContains('d', $a, 'Not a callback function');
        $this->assertContains('e', $a);
    }

    /**
     * @covers Respect\Rest\Routines\CallbackList::hasKey
     */
    public function testHasKey()
    {
        $this->assertTrue($this->object->hasKey('a'));
        $this->assertTrue($this->object->hasKey('b'));
        $this->assertTrue($this->object->hasKey('c'));
        $this->assertFalse($this->object->hasKey('d'));
        $this->assertTrue($this->object->hasKey('e'));
    }

    /**
     * @covers Respect\Rest\Routines\CallbackList::filterKeysContain
     */
    public function testFilterKeysContain()
    {
        $a = $this->object->filterKeysContain('b');
        $this->assertCount(1, $a);
        $this->assertNotContains('a', $a);
        $this->assertContains('b', $a);
        $this->assertNotContains('c', $a);
        $this->assertNotContains('d', $a);
        $this->assertNotContains('e', $a);
    }

    /**
     * @covers Respect\Rest\Routines\CallbackList::filterKeysNotContain
     */
    public function testFilterKeysNotContain()
    {
        $a = $this->object->filterKeysNotContain('b');
        $this->assertCount(3, $a);
        $this->assertContains('a', $a);
        $this->assertNotContains('b', $a);
        $this->assertContains('c', $a);
        $this->assertNotContains('d', $a);
        $this->assertContains('e', $a);
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

