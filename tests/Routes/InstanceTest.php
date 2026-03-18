<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use DateTime;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Routes\Instance;

/** @covers Respect\Rest\Routes\Instance */
final class InstanceTest extends TestCase
{
    /** @covers Respect\Rest\Routes\Instance::getReflection */
    public function test_getReflection_should_return_instance_of_current_routed_class(): void
    {
        $route = new Instance('any', '/', new DateTime());
        $refl = $route->getReflection('format');
        self::assertInstanceOf('ReflectionMethod', $refl);
    }
}
