<?php

namespace Respect\Rest\Routines;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Respect\Rest\Request;

/**
 * @covers Respect\Rest\Routines\AbstractCallbackMediator
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class AbstractCallbackMediatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var AbstractCallbackMediator
     */
    protected $neg;

    protected function setUp(): void
    {
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $this->neg = new Negotiator();

        $a = ['a'=>['a']];
        $this->neg->getMediated($a);
    }

    protected function tearDown(): void
    {
        unset($this->neg);
    }

    /**
     * @covers Respect\Rest\Routines\Negotiator
     */
    public function testNegatiatorMock()
    {
        $neg =  new Negotiator();
        $a = ['ZZ'=>['ZZ']];
        $this->assertTrue($neg->getMediated($a));
        $this->assertContains('ZZ', $neg->outcome);
        $this->assertTrue($neg->outcome['approved']);
    }
    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::identifyRequested
     */
    public function testIdentifyRequested()
    {
        $this->assertContains('a', $this->neg->pubIdentifyRequested(new Request(new ServerRequest('GET', '/'))));
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::considerProvisions
     */
    public function testConsiderProvisions()
    {
        $r = $this->neg->pubIdentifyRequested(new Request(new ServerRequest('GET', '/')));
        $this->assertContains('a', $this->neg->pubConsiderProvisions($r[0]));
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::notifyApproved
     */
    public function testNotifyApproved()
    {
        $asrt = 'a';
        $r = $this->neg->pubIdentifyRequested(new Request(new ServerRequest('GET', '/')));
        $p = $this->neg->pubConsiderProvisions($r[0]);
        $this->neg->pubNotifyApproved($r[0],$p[0]);
        $this->assertContains($asrt, $this->neg->outcome);
        $this->assertEquals($asrt, $this->neg->outcome['requested']);
        $this->assertEquals($asrt, $this->neg->outcome['provided']);
        $this->assertTrue($this->neg->outcome['approved']);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::notifyDeclined
     */
    public function testNotifyDeclined()
    {
        $asrt = 'a';
        $r = $this->neg->pubIdentifyRequested(new Request(new ServerRequest('GET', '/')));
        $p = $this->neg->pubConsiderProvisions($r[0]);
        $this->neg->pubNotifyDeclined($r[0],$p[0]);
        $this->assertContains($asrt, $this->neg->outcome);
        $this->assertEquals($asrt, $this->neg->outcome['requested']);
        $this->assertEquals($asrt, $this->neg->outcome['provided']);
        $this->assertFalse($this->neg->outcome['approved']);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::when
     */
    public function testWhen()
    {
        $neg =  new Negotiator();
        $asrt = 'h';
        $a = [$asrt=>['h','rc','tt','ZZ','uu']];
        $this->assertTrue($neg->getMediated($a));
        $this->assertContains($asrt, $neg->outcome);
        $this->assertEquals($asrt, $neg->outcome['requested']);
        $this->assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'rc';
        $a = [$asrt=>['h','rc','tt','ZZ','uu']];
        $this->assertTrue($neg->getMediated($a));
        $this->assertContains($asrt, $neg->outcome);
        $this->assertEquals($asrt, $neg->outcome['requested']);
        $this->assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'tt';
        $a = [$asrt=>['h','rc','tt','ZZ','uu']];
        $this->assertTrue($neg->getMediated($a));
        $this->assertContains($asrt, $neg->outcome);
        $this->assertEquals($asrt, $neg->outcome['requested']);
        $this->assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'ZZ';
        $a = [$asrt=>['h','rc','tt','ZZ','uu']];
        $this->assertTrue($neg->getMediated($a));
        $this->assertContains($asrt, $neg->outcome);
        $this->assertEquals($asrt, $neg->outcome['requested']);
        $this->assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'uu';
        $a = [$asrt=>['h','rc','tt','ZZ','uu']];
        $this->assertTrue($neg->getMediated($a));
        $this->assertContains($asrt, $neg->outcome);
        $this->assertEquals($asrt, $neg->outcome['requested']);
        $this->assertEquals($asrt, $neg->outcome['provided']);
        $this->assertTrue($neg->outcome['approved']);
        $a = ['abc'=>['h','rc','tt','ZZ','uu']];
        $this->assertFalse($neg->getMediated($a));
        $this->assertFalse($neg->outcome['approved']);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::mediate
     * @covers Respect\Rest\Request
     * @covers Respect\Rest\Routines\AbstractCallbackList
     */
    public function testMediate()
    {
        $neg =  new Negotiator();
        $a = ['ZZ'=>['ZZ']];
        $this->assertTrue($neg->getMediated($a));
        $this->assertContains('ZZ', $neg->outcome);
        $this->assertTrue($neg->outcome['approved']);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::authorize
     */
    public function testAuthorize()
    {
        $r = $this->neg->pubIdentifyRequested(new Request(new ServerRequest('GET', '/')));
        $p = $this->neg->pubConsiderProvisions($r[0]);
        $this->assertTrue($this->neg->pubAuthorize($r[0],$p[0]));
        $this->assertFalse($this->neg->pubAuthorize($r[0],$p[0].'a'));
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::mediate
     */
    public function test_requested_exception(): void{
        $neg = new Negotiator();
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Requests must be an array of 0 to many.');
        $neg->getMediated('not an array');
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::mediate
     */
    public function test_provisions_exception(): void{
        $neg = new Negotiator();
        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Provisions must be an array of 0 to many.');
        $neg->getMediated(['a'=>'not an array']);
    }
}

/**
 * Mock Test instance
 */
class Negotiator extends AbstractCallbackMediator {
    public $decisionmap = [],
        $outcome = [];

    public function __construct ()
    {
        parent::__construct(['a' => 'is_numeric']);
    }


    protected function identifyRequested(Request $request, $params)
    {
        if (is_array($this->decisionmap))
            return array_keys($this->decisionmap);
        else
            $this->decisionmap;
    }
    protected function considerProvisions($requested)
    {
        return !empty($this->decisionmap[$requested]) ? $this->decisionmap[$requested] : [];
    }
    protected function notifyApproved($requested, $provided, Request $request, $params)
    {
        $this->outcome = [
            'approved' => true,
            'requested' => $requested,
            'provided' => $provided,
        ];
    }
    protected function notifyDeclined($requested, $provided, Request $request, $params)
    {
        $this->outcome = [
            'approved' => false,
            'requested' => $requested,
            'provided' => $provided,
        ];
    }
    public function pubIdentifyRequested( $request =null, $params=null)
    {
        return $this->identifyRequested(new Request(new ServerRequest('GET', '/')), $params=null);
    }
    public function pubConsiderProvisions($requested)
    {
        return $this->considerProvisions($requested);
    }
    public function pubNotifyApproved($requested, $provided,  $request = null, $params = null)
    {
        $this->notifyApproved($requested, $provided, new Request(new ServerRequest('GET', '/')), $params=null);
    }
    public function pubNotifyDeclined($requested, $provided,  $request =null, $params=null)
    {
        $this->notifyDeclined($requested, $provided, new Request(new ServerRequest('GET', '/')), $params=null);
    }
    public function pubAuthorize($requested, $provided)
    {
        return $this->authorize($requested, $provided);
    }
    public function getMediated ($decisionmap)
    {
        $this->decisionmap = $decisionmap;
        $this->outcome = [];
        return $this->when(new Request(new ServerRequest('GET', '/')), []);
    }

}

