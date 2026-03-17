<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use DateTime;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Routes\Factory;

/** @covers Respect\Rest\Routes\Factory */
final class FactoryTest extends TestCase
{
    /** @covers Respect\Rest\Routes\Factory::getReflection */
    public function test_getReflection_should_return_instance_of_current_routed_class(): void
    {
        $route = new Factory('any', '/', 'DateTime', static function () {
            return new DateTime();
        });
        $refl = $route->getReflection('format');
        self::assertInstanceOf('ReflectionMethod', $refl);
    }
}
