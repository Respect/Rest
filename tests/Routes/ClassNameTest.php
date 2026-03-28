<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use PHPUnit\Framework\TestCase;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Fluent\Resolvers\Ucfirst;
use Respect\Rest\Routes\ClassName;
use Respect\Rest\Routines\Routinable;

/** @covers Respect\Rest\Routes\ClassName */
final class ClassNameTest extends TestCase
{
    /** @covers Respect\Rest\Routes\ClassName::getReflection */
    public function test_getReflection_should_return_instance_of_current_routed_class(): void
    {
        $lookup = new NamespaceLookup(new Ucfirst(), Routinable::class, 'Respect\\Rest\\Routines');
        $route = new ClassName($lookup, 'any', '/', 'DateTime');
        $refl = $route->getReflection('format');
        self::assertInstanceOf('ReflectionMethod', $refl);
    }

    /** @covers Respect\Rest\Routes\ClassName::getReflection */
    public function test_getReflection_should_return_instance_make_it_snap(): void
    {
        $lookup = new NamespaceLookup(new Ucfirst(), Routinable::class, 'Respect\\Rest\\Routines');
        $route = new ClassName($lookup, 'any', '/', 'DateTime');
        $refl = $route->getReflection('oXoXoXoXoXo');
        self::assertNull($refl);
    }
}
