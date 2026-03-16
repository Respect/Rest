<?php
declare(strict_types=1);

namespace Respect\Rest\Routes;

use Nyholm\Psr7\ServerRequest;
use Respect\Rest\Request;

/**
 * @covers Respect\Rest\Routes\StaticValue
 */
final class StaticValueTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers Respect\Rest\Routes\StaticValue::getReflection
     */
    function test_getReflection_should_return_instance_of_current_routed_class()
    {
        $route = new StaticValue('any', '/', ['foo']);
        $refl = $route->getReflection('format');
        self::assertInstanceOf('ReflectionMethod', $refl);
    }
    /**
     * @covers Respect\Rest\Routes\StaticValue::runTarget
     */
    function test_runTarget_returns_value()
    {
        $route = new StaticValue('any', '/', ['foo']);
        $p = [''];
        $request = new Request(new ServerRequest('GET', '/'));
        self::assertEquals(['foo'], $route->runTarget('get', $p, $request));
    }
}