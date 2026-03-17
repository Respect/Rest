<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routines\Through;

/** @covers Respect\Rest\Routines\Through */
final class ThroughTest extends TestCase
{
    protected Through $object;

    protected function setUp(): void
    {
        $this->object = new Through(static function () {
            return 'from through callback';
        });
    }

    /** @covers Respect\Rest\Routines\Through::through */
    public function testThrough(): void
    {
        $context = new DispatchContext(new ServerRequest('GET', '/'));
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
