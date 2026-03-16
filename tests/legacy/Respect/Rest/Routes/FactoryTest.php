<?php
declare(strict_types=1);

namespace Respect\Rest\Routes;

use \Respect\Rest\Routable;
use \Respect\Rest\Router;

/**
 * @covers Respect\Rest\Routes\Factory
 */
final class FactoryTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @covers Respect\Rest\Routes\Factory::getReflection
     */
    function test_getReflection_should_return_instance_of_current_routed_class()
    {
        $route = new Factory('any', '/', 'DateTime', function() {return new \DateTime;});
        $refl = $route->getReflection('format');
        self::assertInstanceOf('ReflectionMethod', $refl);
    }
}
