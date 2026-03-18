<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routines\Through;

/** @covers Respect\Rest\Routines\Through */
final class ThroughTest extends TestCase
{
    protected Through $object;

    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->object = new Through(static function () {
            return 'from through callback';
        });
    }

    /** @covers Respect\Rest\Routines\Through::through */
    public function testThrough(): void
    {
        $context = new DispatchContext(
            new ServerRequest('GET', '/'),
            $this->factory,
        );
        $params = [];
        $alias = &$this->object;
        self::assertEquals(
            'from through callback',
            $alias->through($context, $params),
        );
    }

    protected function tearDown(): void
    {
        unset($this->object);
    }
}
