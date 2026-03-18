<?php

declare(strict_types=1);

namespace Respect\Rest\Test;

use Closure;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionFunction;
use Respect\Rest\DispatchContext;
use Respect\Rest\HttpFactories;
use Respect\Rest\Responder;
use Respect\Rest\Routes;
use Respect\Rest\Routines;
use RuntimeException;

use function array_unique;
use function array_walk;
use function func_get_args;
use function implode;
use function is_callable;
use function md5;
use function rand;
use function str_replace;
use function strtolower;

/** @covers Respect\Rest\DispatchContext */
#[AllowMockObjectsWithoutExpectations]
final class DispatchContextTest extends TestCase
{
    private HttpFactories $httpFactories;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->httpFactories = new HttpFactories($factory, $factory);
    }

    /** @covers  Respect\Rest\DispatchContext::__construct */
    public function testIsPossibleToConstructUsingValuesFromSuperglobals(): DispatchContext
    {
        $_SERVER['REQUEST_URI'] = '/users';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $context = $this->newContext(new ServerRequest('GET', '/users'));

        self::assertEquals(
            '/users',
            $context->path(),
            'Should inherit the path from $_SERVER',
        );
        self::assertEquals(
            'GET',
            $context->method(),
            'Should inherit the method from $_SERVER',
        );

        return $context;
    }

    /** @covers  Respect\Rest\DispatchContext::__construct */
    public function testIsPossibleToConstructWithCustomMethod(): void
    {
        $_SERVER['REQUEST_URI'] = '/documents';
        $_SERVER['REQUEST_METHOD'] = 'NOTPATCH';

        $context = $this->newContext(new ServerRequest('PATCH', '/documents'));

        self::assertNotEquals(
            'NOTPATCH',
            $context->method(),
            'Should ignore $_SERVER if method was passed on constructor',
        );
        self::assertEquals(
            'PATCH',
            $context->method(),
            'Should use constructor method',
        );
    }

    /** @covers  Respect\Rest\DispatchContext::__construct */
    public function testIsPossibleToConstructWithCustomUri(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/videos';

        $context = $this->newContext(new ServerRequest('POST', '/images'));

        self::assertNotEquals(
            '/videos',
            $context->path(),
            'Should ignore $_SERVER if path was passed on constructor',
        );

        self::assertEquals(
            '/images',
            $context->path(),
            'Should use constructor path',
        );
    }

    /** @covers      Respect\Rest\DispatchContext::__construct */
    public function testAbsoluteUrisShouldBeParsedToExtractThePathOnConstructor(): void
    {
        $_SERVER['REQUEST_URI'] = 'http://google.com/search?q=foo';

        $context = new DispatchContext(
            new ServerRequest('GET', 'http://google.com/search?q=foo'),
            $this->httpFactories->responses,
            $this->httpFactories->streams,
        );

        self::assertNotEquals(
            'http://google.com/search?q=foo',
            $context->path(),
            'Absolute URI should not be on path',
        );

        self::assertEquals(
            '/search',
            $context->path(),
            'Path should be extracted from absolute URI',
        );
    }

    /** @covers  Respect\Rest\DispatchContext::response */
    #[Depends('testIsPossibleToConstructUsingValuesFromSuperglobals')]
    public function testResponseIsNullWithoutSettingARoute(DispatchContext $context): void
    {
        $response = $context->response();

        self::assertNull($response, 'Response should be null if no route is set');
    }

    /** @covers  Respect\Rest\DispatchContext::response */
    #[Depends('testIsPossibleToConstructUsingValuesFromSuperglobals')]
    public function testContextIsAbleToDeliverAResponseWithoutSettingPathParams(DispatchContext $context): void
    {
        $context->route = $this->getMockForRoute(
            'GET',
            '/notebooks',
            ['Vaio', 'MacBook', 'ThinkPad'],
        );
        $response = $context->response();

        self::assertNotNull($response, 'Response should not be null');
        self::assertEquals(
            '["Vaio","MacBook","ThinkPad"]',
            (string) $response->getBody(),
            'Response body should contain JSON-encoded array from runTarget',
        );
    }

    /** @covers  Respect\Rest\DispatchContext::response */
    #[Depends('testIsPossibleToConstructUsingValuesFromSuperglobals')]
    public function testContextIsAbleToDeliverAResponseUsingPreviouslySetPathParams(DispatchContext $context): void
    {
        $context->route = $this->getMockForRoute(
            'GET',
            '/printers',
            'Some Printers Response',
            'GET',
            ['dpi', 'price'],
        );
        $context->params = ['dpi', 'price'];
        $response = $context->response();

        self::assertNotNull($response);
        self::assertEquals(
            'Some Printers Response',
            (string) $response->getBody(),
            'Response should return the route target when using previously set path params',
        );
    }

    /** @covers  Respect\Rest\DispatchContext::forward */
    public function testForwardReplacesRouteAndReturnsResponse(): void
    {
        $context = $this->newContext(new ServerRequest('GET', '/users/alganet/lists'));
        $inactiveRoute  = $this->getMockForRoute('GET', '/users/alganet/lists');
        $forwardedRoute = $this->getMockForRoute('GET', '/lists/12345', 'Some list items');
        $context->route = $inactiveRoute;
        $context->forward($forwardedRoute);

        self::assertNotSame(
            $inactiveRoute,
            $context->route,
            'After forwarding a route, the previous one should not be in the route attribute',
        );
        self::assertSame(
            $forwardedRoute,
            $context->route,
            'After forwarding a route, the forwarded route should be in the route attribute',
        );
    }

    /** @covers       Respect\Rest\DispatchContext::response */
    #[Depends('testForwardReplacesRouteAndReturnsResponse')]
    #[DataProvider('providerForUserImplementedForwards')]
    public function testDeveloperCanForwardRoutesByReturningThemOnTheirImplementation(
        string $scenario,
    ): void {
        $internallyForwardedRoute = $this->getMockForRoute(
            'GET',
            '/candies/cupcakes',
            'Delicious Cupcake Internally Forwarded',
        );

        $forwardWithTarget = $this->getMockForRoute(
            'GET',
            '/cupcakes',
            static function () use ($internallyForwardedRoute) {
                return $internallyForwardedRoute;
            },
        );

        $forwardWithByRoutine = $this->getMockForRoute(
            'GET',
            '/cereals',
            'Nice Cereals',
        );

        switch ($scenario) {
            case 'forwardWithTarget':
                $userImplementedRoute = $forwardWithTarget;
                break;
            case 'forwardWithByRoutine':
                $byRoutine = $this->getMockForRoutine('ProxyableBy');
                $byRoutine->expects($this->once())
                    ->method('by')
                    ->willReturn($internallyForwardedRoute);
                $forwardWithByRoutine->appendRoutine($byRoutine);
                $userImplementedRoute = $forwardWithByRoutine;
                break;
            default:
                self::fail('Unknown provider scenario');
        }

        $context = $this->newContext(new ServerRequest('GET', '/cupcakes'));
        $context->route = $userImplementedRoute;
        $response = $context->response();

        self::assertNotNull($response);
        self::assertSame('Delicious Cupcake Internally Forwarded', (string) $response->getBody());
    }

    /** @return array<int, array<int, string>> */
    public static function providerForUserImplementedForwards(): array
    {
        return [
            ['forwardWithTarget'],
            ['forwardWithByRoutine'],
        ];
    }

    /** @covers Respect\Rest\DispatchContext::response */
    public function testDeveloperCanAbortRequestReturningFalseOnByRoutine(): void
    {
        $context = $this->newContext(new ServerRequest('GET', '/protected-area'));
        $route = $this->getMockForRoute('GET', '/protected-area', 'Protected Content!!!');
        $routine = $this->getMockForRoutine('ProxyableBy');
        $routine->expects($this->once())
            ->method('by')
            ->willReturn(false);
        $route->appendRoutine($routine);
        $context->route = $route;

        $response = $context->response();

        self::assertNotNull($response);
        self::assertNotSame(
            'Protected Content!!!',
            (string) $response->getBody(),
            'Response should not be the protected content.',
        );
        self::assertInstanceOf(
            ResponseInterface::class,
            $response,
            'Response should be a ResponseInterface when aborting response.',
        );
        self::assertEmpty(
            (string) $response->getBody(),
            'Response body should be empty when aborting response.',
        );
    }

    /** @covers Respect\Rest\DispatchContext::response */
    public function testDeveloperCanReturnResponseInterfacesOnByRoutine(): void
    {
        $factory = new Psr17Factory();
        $context = $this->newContext(new ServerRequest('GET', '/protected-area'));
        $route = $this->getMockForRoute('GET', '/protected-area', 'Protected Content!!!');
        $routine = $this->getMockForRoutine('ProxyableBy');
        $routine->expects($this->once())
            ->method('by')
            ->willReturn(
                $factory
                    ->createResponse(202)
                    ->withHeader('X-Flow', 'by')
                    ->withBody($factory->createStream('blocked by routine')),
            );
        $route->appendRoutine($routine);
        $context->route = $route;

        $response = $context->response();

        self::assertNotNull($response);
        self::assertSame(202, $response->getStatusCode());
        self::assertSame('by', $response->getHeaderLine('X-Flow'));
        self::assertSame('blocked by routine', (string) $response->getBody());
    }

    /** @covers Respect\Rest\DispatchContext::response */
    public function testDeveloperCanReturnCallablesToProcessOutputAfterTargetRuns(): void
    {
        $context = $this->newContext(new ServerRequest('GET', '/logs'));
        $route = $this->getMockForRoute(
            'GET',
            '/logs',
            'user-deleted-something',
            'GET',
            $expectedParams = [],
        );
        $routine = $this->getMockForRoutine('ProxyableThrough');
        $routine->expects($this->once())
            ->method('through')
            ->willReturn(static function ($thatLogStubReturnedAbove) {
                    return str_replace('-', ' ', $thatLogStubReturnedAbove);
            });
        $route->appendRoutine($routine);
        $context->route = $route;
        $response = $context->response();

        self::assertNotNull($response);
        self::assertSame(
            'user deleted something',
            (string) $response->getBody(),
            'We passed a callback that replaced - for spaces, response should be passed to it.',
        );
    }

    /**
     * @param array<int, mixed> $params
     *
     * @covers       Respect\Rest\DispatchContext::response
     */
    #[DataProvider('providerForParamSyncedRoutines')]
    public function testParamSyncedRoutinesShouldAllReferenceTheSameValuesByTheirNames(
        string $scenario,
        array $params,
    ): void {
        $context = $this->newContext(new ServerRequest('GET', '/version'));
        $context->params = $params;

        $route = $this->getMockForRoute(
            'GET',
            '/version',
            'MySoftwareName',
            'GET',
            $params,
        );

        $checkers = [];
        switch ($scenario) {
            case 'pureSynced':
                $checkers = [
                    static function ($majorVersion, $minorVersion, $patchVersion): void {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertSame(5, $patchVersion);
                    },
                    static function ($patchVersion, $minorVersion, $majorVersion): void {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertSame(5, $patchVersion);
                    },
                    static function ($majorVersion): void {
                        self::assertCount(1, func_get_args());
                        self::assertSame(15, $majorVersion);
                    },
                    static function (): void {
                        self::assertCount(0, func_get_args());
                    },
                ];
                break;
            case 'pureNulls':
                $checkers = [
                    static function ($majorVersion, $minorVersion, $patchVersion): void {
                        self::assertCount(3, func_get_args());
                        self::assertNull($majorVersion);
                        self::assertNull($minorVersion);
                        self::assertNull($patchVersion);
                    },
                    static function ($patchVersion, $minorVersion, $majorVersion): void {
                        self::assertCount(3, func_get_args());
                        self::assertNull($majorVersion);
                        self::assertNull($minorVersion);
                        self::assertNull($patchVersion);
                    },
                    static function ($patchVersion, $minorVersion, $majorVersion): void {
                        self::assertCount(3, func_get_args());
                        self::assertNull($majorVersion);
                        self::assertNull($minorVersion);
                        self::assertNull($patchVersion);
                    },
                    static function ($majorVersion): void {
                        self::assertCount(1, func_get_args());
                        self::assertNull($majorVersion);
                    },
                ];
                break;
            case 'pureDefaults':
                $checkers = [
                    static function ($majorVersion = 15, $minorVersion = 10, $patchVersion = 5): void {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertSame(5, $patchVersion);
                    },
                    static function ($patchVersion = 5, $minorVersion = 10, $majorVersion = 15): void {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertSame(5, $patchVersion);
                    },
                    static function ($majorVersion = 15): void {
                        self::assertCount(1, func_get_args());
                        self::assertSame(15, $majorVersion);
                    },
                    static function (): void {
                        self::assertCount(0, func_get_args());
                    },
                ];
                break;
            case 'mixed':
                $checkers = [
                    static function ($majorVersion, $minorVersion, $patchVersion = 5): void {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertSame(5, $patchVersion);
                    },
                    static function ($majorVersion, $minorVersion, $patchVersion): void {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertNull($patchVersion);
                    },
                    static function (): void {
                        self::assertCount(0, func_get_args());
                    },
                ];
                break;
            default:
                self::fail('Unknown provider scenario');
        }

        foreach ($checkers as $checker) {
            $route->appendRoutine($this->getMockForProxyableRoutine($route, 'By', $checker));
        }

        $context->route = $route;

        $response = $context->response();

        self::assertNotNull($response);
        self::assertEquals('MySoftwareName', (string) $response->getBody());
    }

    /** @return array<int, array<int, mixed>> */
    public static function providerForParamSyncedRoutines(): array
    {
        return [
            ['pureSynced', [15, 10, 5]],
            ['pureNulls', []],
            ['pureDefaults', []],
            ['mixed', [15, 10]],
        ];
    }

    public function testConvertingToStringCallsResponse(): void
    {
        $context = $this->newContext(new ServerRequest('GET', '/users/alganet/lists'));
        $context->route = $this->getMockForRoute('GET', '/users/alganet/lists', 'Some list items');
        $toString = (string) $context;

        self::assertSame('Some list items', $toString);
    }

    public function testContextUsesDraftHeadersAndDeferredDefaultsWithoutRoute(): void
    {
        $context = $this->newContext(new ServerRequest('GET', '/users'));
        $context->setResponseHeader('Content-Type', 'text/plain');
        $context->appendResponseHeader('Vary', 'accept');
        $context->appendResponseHeader('Vary', 'accept-language');
        $context->defaultResponseHeader('Content-Location', '/users');

        $response = $context->response();

        self::assertNotNull($response);
        self::assertSame('text/plain', $response->getHeaderLine('Content-Type'));
        self::assertSame('/users', $response->getHeaderLine('Content-Location'));
        self::assertSame('accept, accept-language', $response->getHeaderLine('Vary'));
    }

    public function testContextAcceptsAnInjectedResponder(): void
    {
        $context = $this->newContext(new ServerRequest('GET', '/users/alganet/lists'));
        $factory = new Psr17Factory();
        $context->setResponder(new Responder($factory, $factory));
        $context->route = $this->getMockForRoute('GET', '/users/alganet/lists', 'Some list items');

        $response = $context->response();

        self::assertNotNull($response);
        self::assertSame('Some list items', (string) $response->getBody());
    }

    public function test_unsynced_param_comes_as_null(): void
    {
        $context = $this->newContext(new ServerRequest('GET', '/'));
        $context->route = new Routes\Callback('GET', '/', static function ($bar) {
            return 'ok';
        });
        $args = [];
        $routine = new Routines\By(static function ($foo, $bar, $baz) use (&$args): void {
            $args = func_get_args();
        });
        $context->route->appendRoutine($routine);
        $dummy = ['bar'];
        $context->routineCall('by', 'GET', $routine, $dummy, $context->route);
        self::assertEquals([null, 'bar', null], $args);
    }

    public function test_exception_rethrown_when_no_exception_route_matches(): void
    {
        $context = $this->newContext(new ServerRequest('GET', '/'));
        $context->route = $this->getMockForRoute(
            'GET',
            '/',
            static function (): never {
                throw new RuntimeException('boom');
            },
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $context->response();
    }

    /**
     * @param Routes\AbstractRoute&MockObject $route
     *
     * @return MockObject&Routines\Routinable
     */
    protected function getMockForProxyableRoutine(
        Routes\AbstractRoute $route,
        string $name,
        Closure $implementation,
    ): MockObject {
        $routine = $this->getMockForRoutine(['Proxyable' . $name, 'ParamSynced']);
        $reflection = new ReflectionFunction($implementation);
        $route->expects($this->any())
            ->method('getReflection')
            ->with('GET')
            ->willReturn($reflection);
        $routine->expects($this->any())
            ->method('getParameters')
            ->willReturn($reflection->getParameters());
        $routine->expects($this->any())
            ->method(strtolower($name) ?: $name) // @phpstan-ignore argument.type
            ->willReturnCallback(static function ($request, $params) use ($implementation) {
                return $implementation(...$params);
            });

        return $routine;
    }

    /**
     * @param array<int, mixed> $targetParams
     *
     * @return Routes\AbstractRoute&MockObject
     */
    protected function getMockForRoute(
        string $method,
        string $pattern,
        mixed $target = null,
        string $targetMethod = 'GET',
        array $targetParams = [],
    ): Routes\AbstractRoute {
        $hasTarget = $target !== null;

        $route = $this->getMockBuilder('Respect\Rest\Routes\AbstractRoute')
            ->setConstructorArgs([$method, $pattern])
            ->onlyMethods(['getReflection', 'runTarget'])
            ->getMock();

        if ($hasTarget) {
            if ($targetParams) {
                $expectation = $route->expects($this->any())
                    ->method('runTarget')
                    ->with($targetMethod, $targetParams);
            } else {
                $expectation = $route->expects($this->any())
                    ->method('runTarget');
            }

            if (is_callable($target)) {
                $expectation->willReturnCallback($target);
            } else {
                $expectation->willReturn($target);
            }
        }

        return $route;
    }

    /**
     * @param string|array<int, string> $interfaceList
     *
     * @return MockObject&Routines\Routinable
     */
    protected function getMockForRoutine(string|array $interfaceList): MockObject
    {
        $interfaceName = 'GeneratedInterface' . md5((string) rand());

        $interfaceList = (array) $interfaceList;
        array_walk($interfaceList, static function (&$interfaceSuffix): void {
            $interfaceSuffix = 'Respect\\Rest\\Routines\\' . $interfaceSuffix;
        });
        $interfaceList[] = 'Respect\Rest\Routines\Routinable';
        $interfaceList = array_unique($interfaceList);
        $interfaceList = implode(',', $interfaceList);

        eval('interface ' . $interfaceName . ' extends ' . $interfaceList . '{}');

        /** @var class-string $className */
        $className = $interfaceName;

        return $this->createMock($className); // @phpstan-ignore return.type
    }

    private function newContext(ServerRequest $request): DispatchContext
    {
        return new DispatchContext($request, $this->httpFactories->responses, $this->httpFactories->streams);
    }
}
