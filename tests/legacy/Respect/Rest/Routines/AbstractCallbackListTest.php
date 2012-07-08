<?php
namespace Respect\Rest\Routines;


/**
 * @covers Respect\Rest\Routines\AbstractCallbackList
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class AbstractCallbackListTest extends \PHPUnit_Framework_TestCase
{
    protected $object;

    protected function setUp()
    {
        $ar = array(
                'a' => 'htmlentities',
                'b' => function () { return true; },
                'c' => 'strpos',
                'd' => 'this is invalid',
                'e' => 'is_numeric',
        );

        $this->object = new FunkyAbstractCallbackList($ar);
    }

    protected function tearDown()
    {
        unset($this->object);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackList::executeCallback
     */
    public function testExecuteCallback()
    {
        $this->assertEquals('&lt;p&gt;&lt;/p&gt;',$this->object->funkyExecuteCallback('a',array('<p></p>')));
        $this->assertTrue($this->object->funkyExecuteCallback('b', array()));
        $this->assertFalse($this->object->funkyExecuteCallback('c', array('d','abc')));
        $this->assertTrue($this->object->funkyExecuteCallback('e', array(4)));
    }
    /**
     * @covers Respect\Rest\Routines\AbstractCallbackList::getCallback
     */
    public function testGetCallback()
    {
        $this->assertTrue(is_callable($this->object->funkyGetCallback('a')));
        $this->assertTrue(is_callable($this->object->funkyGetCallback('b')));
        $this->assertTrue(is_callable($this->object->funkyGetCallback('c')));
        $this->assertTrue(is_callable($this->object->funkyGetCallback('e')));
    }
    /**
     * @covers Respect\Rest\Routines\AbstractCallbackList::__construct
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
     * @covers Respect\Rest\Routines\AbstractCallbackList::getKeys
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
     * @covers Respect\Rest\Routines\AbstractCallbackList::hasKey
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
     * @covers Respect\Rest\Routines\AbstractCallbackList::filterKeysContain
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
     * @covers Respect\Rest\Routines\AbstractCallbackList::filterKeysNotContain
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

class FunkyAbstractCallbackList extends AbstractCallbackList{
    public function funkyExecuteCallback($key, $params) {
        return $this->executeCallback($key, $params);
    }
    public function funkyGetCallback($key) {
        return $this->getCallback($key);
    }
}

