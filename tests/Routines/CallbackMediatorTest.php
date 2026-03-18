<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\Test\Stubs\Negotiator;
use TypeError;

/** @covers Respect\Rest\Routines\AbstractCallbackMediator */
final class CallbackMediatorTest extends TestCase
{
    protected Negotiator $neg;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->neg = new Negotiator();

        $a = ['a' => ['a']];
        $this->neg->getMediated($a);
    }

    public function testNegatiatorMock(): void
    {
        $neg = new Negotiator();
        $a = ['ZZ' => ['ZZ']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains('ZZ', $neg->outcome);
        self::assertTrue($neg->outcome['approved']);
    }

    /** @covers Respect\Rest\Routines\AbstractCallbackMediator::identifyRequested */
    public function testIdentifyRequested(): void
    {
        self::assertContains(
            'a',
            $this->neg->pubIdentifyRequested($this->newContext()),
        );
    }

    /** @covers Respect\Rest\Routines\AbstractCallbackMediator::considerProvisions */
    public function testConsiderProvisions(): void
    {
        $r = $this->neg->pubIdentifyRequested(
            $this->newContext(),
        );
        self::assertContains('a', $this->neg->pubConsiderProvisions($r[0]));
    }

    /** @covers Respect\Rest\Routines\AbstractCallbackMediator::notifyApproved */
    public function testNotifyApproved(): void
    {
        $asrt = 'a';
        $r = $this->neg->pubIdentifyRequested(
            $this->newContext(),
        );
        $p = $this->neg->pubConsiderProvisions($r[0]);
        $this->neg->pubNotifyApproved($r[0], $p[0]);
        self::assertContains($asrt, $this->neg->outcome);
        self::assertEquals($asrt, $this->neg->outcome['requested']);
        self::assertEquals($asrt, $this->neg->outcome['provided']);
        self::assertTrue($this->neg->outcome['approved']);
    }

    /** @covers Respect\Rest\Routines\AbstractCallbackMediator::notifyDeclined */
    public function testNotifyDeclined(): void
    {
        $asrt = 'a';
        $r = $this->neg->pubIdentifyRequested(
            $this->newContext(),
        );
        $p = $this->neg->pubConsiderProvisions($r[0]);
        $this->neg->pubNotifyDeclined($r[0], $p[0]);
        self::assertContains($asrt, $this->neg->outcome);
        self::assertEquals($asrt, $this->neg->outcome['requested']);
        self::assertEquals($asrt, $this->neg->outcome['provided']);
        self::assertFalse($this->neg->outcome['approved']);
    }

    /** @covers Respect\Rest\Routines\AbstractCallbackMediator::when */
    public function testWhen(): void
    {
        $neg = new Negotiator();
        $asrt = 'h';
        $a = [$asrt => ['h', 'rc', 'tt', 'ZZ', 'uu']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains($asrt, $neg->outcome);
        self::assertEquals($asrt, $neg->outcome['requested']);
        self::assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'rc';
        $a = [$asrt => ['h', 'rc', 'tt', 'ZZ', 'uu']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains($asrt, $neg->outcome);
        self::assertEquals($asrt, $neg->outcome['requested']);
        self::assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'tt';
        $a = [$asrt => ['h', 'rc', 'tt', 'ZZ', 'uu']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains($asrt, $neg->outcome);
        self::assertEquals($asrt, $neg->outcome['requested']);
        self::assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'ZZ';
        $a = [$asrt => ['h', 'rc', 'tt', 'ZZ', 'uu']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains($asrt, $neg->outcome);
        self::assertEquals($asrt, $neg->outcome['requested']);
        self::assertEquals($asrt, $neg->outcome['provided']);
        $asrt = 'uu';
        $a = [$asrt => ['h', 'rc', 'tt', 'ZZ', 'uu']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains($asrt, $neg->outcome);
        self::assertEquals($asrt, $neg->outcome['requested']);
        self::assertEquals($asrt, $neg->outcome['provided']);
        self::assertTrue($neg->outcome['approved']);
        $a = ['abc' => ['h', 'rc', 'tt', 'ZZ', 'uu']];
        self::assertFalse($neg->getMediated($a));
        self::assertFalse($neg->outcome['approved']);
    }

    /**
     * @covers Respect\Rest\Routines\AbstractCallbackMediator::mediate
     * @covers Respect\Rest\DispatchContext
     * @covers Respect\Rest\Routines\CallbackList
     */
    public function testMediate(): void
    {
        $neg = new Negotiator();
        $a = ['ZZ' => ['ZZ']];
        self::assertTrue($neg->getMediated($a));
        self::assertContains('ZZ', $neg->outcome);
        self::assertTrue($neg->outcome['approved']);
    }

    /** @covers Respect\Rest\Routines\AbstractCallbackMediator::authorize */
    public function testAuthorize(): void
    {
        $r = $this->neg->pubIdentifyRequested(
            $this->newContext(),
        );
        $p = $this->neg->pubConsiderProvisions($r[0]);
        self::assertTrue($this->neg->pubAuthorize($r[0], $p[0]));
        self::assertFalse($this->neg->pubAuthorize($r[0], $p[0] . 'a'));
    }

    public function test_requested_non_array_returns_false(): void
    {
        $neg = new Negotiator();
        self::assertFalse($neg->getMediated('not an array'));
    }

    public function test_provisions_exception(): void
    {
        $neg = new Negotiator();
        self::expectException(TypeError::class);
        /** @phpstan-ignore-next-line intentionally passing invalid type to test TypeError */
        $neg->getMediated(['a' => 'not an array']);
    }

    protected function tearDown(): void
    {
        unset($this->neg);
    }

    private function newContext(): DispatchContext
    {
        return new DispatchContext(
            new ServerRequest('GET', '/'),
            $this->factory,
        );
    }
}
