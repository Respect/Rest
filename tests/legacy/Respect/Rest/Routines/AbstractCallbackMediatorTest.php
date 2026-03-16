<?php
declare(strict_types=1);

namespace Respect\Rest\Routines;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Respect\Rest\Request;

/**
 * @covers Respect\Rest\Routines\AbstractCallbackMediator
 * @author Nick Lombard <github@jigsoft.co.za>
 */
final class AbstractCallbackMediatorTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var AbstractCallbackMediator
     */
    protected $neg;

    protected function setUp(): void
    {
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
        self::assertTrue($neg->getMediated($a));
        self::assertContains('ZZ', $neg->outcome);
        self::assertTrue($neg->outcome['approved']);
    }
    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::identifyRequested
     */
    public function testIdentifyRequested()
    {
        self::assertContains('a', $this->neg->pubIdentifyRequested(new Request(new ServerRequest('GET', '/'))));
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::considerProvisions
     */
    public function testConsiderProvisions()
    {
        $r = $this->neg->pubIdentifyRequested(new Request(new ServerRequest('GET', '/')));
        self::assertContains('a', $this->neg->pubConsiderProvisions($r[0]));
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
        self::assertContains($asrt, $this->neg->outcome);
        self::assertEquals($asrt, $this->neg->outcome['requested']);
        self::assertEquals($asrt, $this->neg->outcome['provided']);
        self::assertTrue($this->neg->outcome['approved']);
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
        self::assertContains($asrt, $this->neg->outcome);
        self::assertEquals($asrt, $this->neg->outcome['requested']);
        self::assertEquals($asrt, $this->neg->outcome['provided']);
        self::assertFalse($this->neg->outcome['approved']);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::when
     */
    public function testWhen()
    {
        $neg =  new Negotiator();
        $asrt = 'h';
        $a = [$asrt=>['h','rc','tt','ZZ','uu']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains($asrt, $neg->outcome);
        self::assertEquals($asrt, $neg->outcome['requested']);
        self::assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'rc';
        $a = [$asrt=>['h','rc','tt','ZZ','uu']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains($asrt, $neg->outcome);
        self::assertEquals($asrt, $neg->outcome['requested']);
        self::assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'tt';
        $a = [$asrt=>['h','rc','tt','ZZ','uu']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains($asrt, $neg->outcome);
        self::assertEquals($asrt, $neg->outcome['requested']);
        self::assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'ZZ';
        $a = [$asrt=>['h','rc','tt','ZZ','uu']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains($asrt, $neg->outcome);
        self::assertEquals($asrt, $neg->outcome['requested']);
        self::assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'uu';
        $a = [$asrt=>['h','rc','tt','ZZ','uu']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains($asrt, $neg->outcome);
        self::assertEquals($asrt, $neg->outcome['requested']);
        self::assertEquals($asrt, $neg->outcome['provided']);
        self::assertTrue($neg->outcome['approved']);
        $a = ['abc'=>['h','rc','tt','ZZ','uu']];
        self::assertFalse($neg->getMediated($a));
        self::assertFalse($neg->outcome['approved']);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::mediate
     * @covers Respect\Rest\Request
     * @covers Respect\Rest\Routines\CallbackList
     */
    public function testMediate()
    {
        $neg =  new Negotiator();
        $a = ['ZZ'=>['ZZ']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains('ZZ', $neg->outcome);
        self::assertTrue($neg->outcome['approved']);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::authorize
     */
    public function testAuthorize()
    {
        $r = $this->neg->pubIdentifyRequested(new Request(new ServerRequest('GET', '/')));
        $p = $this->neg->pubConsiderProvisions($r[0]);
        self::assertTrue($this->neg->pubAuthorize($r[0],$p[0]));
        self::assertFalse($this->neg->pubAuthorize($r[0],$p[0].'a'));
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::mediate
     *
     * With typed returns on identifyRequested(), a non-array decisionmap
     * simply causes identifyRequested to return [] (empty array), so the
     * mediation returns false without throwing.
     */
    public function test_requested_non_array_returns_false(): void{
        $neg = new Negotiator();
        self::assertFalse($neg->getMediated('not an array'));
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::mediate
     *
     * With typed returns on considerProvisions(), a non-array value causes
     * a TypeError instead of the previous UnexpectedValueException.
     */
    public function test_provisions_exception(): void{
        $neg = new Negotiator();
        self::expectException(\TypeError::class);
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


    protected function identifyRequested(Request $request, array $params): array
    {
        if (is_array($this->decisionmap)) {
            return array_keys($this->decisionmap);
        }

        return [];
    }
    protected function considerProvisions(string $requested): array
    {
        return !empty($this->decisionmap[$requested]) ? $this->decisionmap[$requested] : [];
    }
    protected function notifyApproved(string $requested, string $provided, Request $request, array $params): void
    {
        $this->outcome = [
            'approved' => true,
            'requested' => $requested,
            'provided' => $provided,
        ];
    }
    protected function notifyDeclined(string $requested, string $provided, Request $request, array $params): void
    {
        $this->outcome = [
            'approved' => false,
            'requested' => $requested,
            'provided' => $provided,
        ];
    }
    public function pubIdentifyRequested( $request =null, $params=[])
    {
        return $this->identifyRequested(new Request(new ServerRequest('GET', '/')), $params);
    }
    public function pubConsiderProvisions($requested)
    {
        return $this->considerProvisions($requested);
    }
    public function pubNotifyApproved($requested, $provided,  $request = null, $params = [])
    {
        $this->notifyApproved($requested, $provided, new Request(new ServerRequest('GET', '/')), $params);
    }
    public function pubNotifyDeclined($requested, $provided,  $request =null, $params=[])
    {
        $this->notifyDeclined($requested, $provided, new Request(new ServerRequest('GET', '/')), $params);
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

