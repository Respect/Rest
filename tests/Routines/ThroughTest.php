<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\HttpFactories;
use Respect\Rest\Routines\Through;

/** @covers Respect\Rest\Routines\Through */
final class ThroughTest extends TestCase
{
    protected Through $object;

    private HttpFactories $httpFactories;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->httpFactories = new HttpFactories($factory, $factory);
        $this->object = new Through(static function () {
            return 'from through callback';
        });
    }

    /** @covers Respect\Rest\Routines\Through::through */
    public function testThrough(): void
    {
        $context = new DispatchContext(
            new ServerRequest('GET', '/'),
            $this->httpFactories->responses,
            $this->httpFactories->streams,
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
