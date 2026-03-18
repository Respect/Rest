<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routes\StaticValue;

/** @covers Respect\Rest\Routes\StaticValue */
final class StaticValueTest extends TestCase
{
    /** @covers Respect\Rest\Routes\StaticValue::getReflection */
    public function test_getReflection_should_return_instance_of_current_routed_class(): void
    {
        $route = new StaticValue('any', '/', ['foo']);
        $refl = $route->getReflection('format');
        self::assertInstanceOf('ReflectionMethod', $refl);
    }

    /** @covers Respect\Rest\Routes\StaticValue::runTarget */
    public function test_runTarget_returns_value(): void
    {
        $route = new StaticValue('any', '/', ['foo']);
        $p = [''];
        $context = new DispatchContext(
            new ServerRequest('GET', '/'),
            new Psr17Factory(),
            new Psr17Factory(),
        );
        self::assertEquals(['foo'], $route->runTarget('get', $p, $context));
    }
}
