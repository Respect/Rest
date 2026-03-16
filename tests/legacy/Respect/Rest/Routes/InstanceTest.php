<?php
declare(strict_types=1);

namespace Respect\Rest\Routes;

/**
 * @covers Respect\Rest\Routes\Instance
 */
final class InstanceTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers Respect\Rest\Routes\Instance::getReflection
     */
    function test_getReflection_should_return_instance_of_current_routed_class()
    {
        $route = new Instance('any', '/', new \DateTime);
        $refl = $route->getReflection('format');
        self::assertInstanceOf('ReflectionMethod', $refl);
    }
}