<?php
declare(strict_types=1);

namespace Respect\Rest\Routes;

/**
 * @covers Respect\Rest\Routes\ClassName
 */
final class ClassNameTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers Respect\Rest\Routes\ClassName::getReflection
     */
    function test_getReflection_should_return_instance_of_current_routed_class()
    {
        $route = new ClassName('any', '/', 'DateTime');
        $refl = $route->getReflection('format');
        self::assertInstanceOf('ReflectionMethod', $refl);
    }
    /**
     * @covers Respect\Rest\Routes\ClassName::getReflection
     */
    function test_getReflection_should_return_instance_make_it_snap()
    {
        $route = new ClassName('any', '/', 'DateTime');
        $refl = $route->getReflection('oXoXoXoXoXo');
        self::assertNull($refl);
    }
}