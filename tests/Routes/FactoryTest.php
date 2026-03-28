<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use DateTime;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Fluent\Factories\NamespaceLookup;
use Respect\Fluent\Resolvers\Ucfirst;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routes\Factory;
use Respect\Rest\Routines\Routinable;
use stdClass;

/** @covers Respect\Rest\Routes\Factory */
final class FactoryTest extends TestCase
{
    /** @covers Respect\Rest\Routes\Factory::getReflection */
    public function test_getReflection_should_return_instance_of_current_routed_class(): void
    {
        $lookup = new NamespaceLookup(new Ucfirst(), Routinable::class, 'Respect\\Rest\\Routines');
        $route = new Factory($lookup, 'any', '/', 'DateTime', static function () {
            return new DateTime();
        });
        $refl = $route->getReflection('format');
        self::assertInstanceOf('ReflectionMethod', $refl);
    }

    public function test_runTarget_throws_when_factory_returns_non_routable(): void
    {
        $lookup = new NamespaceLookup(new Ucfirst(), Routinable::class, 'Respect\\Rest\\Routines');
        $route = new Factory($lookup, 'GET', '/', stdClass::class, static fn() => new stdClass());
        $params = [];
        $context = new DispatchContext(new ServerRequest('GET', '/'), new Psr17Factory());

        $this->expectException(InvalidArgumentException::class);
        $route->runTarget('GET', $params, $context);
    }
}
