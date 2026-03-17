<?php
declare(strict_types=1);

namespace Respect\Rest\Test;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;
use Respect\Rest\Request;
use Respect\Rest\Routes;
use Respect\Rest\Routines;

/**
 * @covers Respect\Rest\Request
 */
#[AllowMockObjectsWithoutExpectations]
final class RequestTest extends TestCase
{
    /**
     * @covers  Respect\Rest\Request::__construct
     */
    public function testIsPossibleToConstructUsingValuesFromSuperglobals()
    {
        $_SERVER['REQUEST_URI'] = '/users';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $request = new Request(new ServerRequest('GET', '/users'));

        self::assertEquals(
            '/users',
            $request->uri,
            'Should inherit the path from $_SERVER'
        );
        self::assertEquals(
            'GET',
            $request->method,
            'Should inherit the method from $_SERVER'
        );

        return $request;
    }

    /**
     * @covers  Respect\Rest\Request::__construct
     */
    public function testIsPossibleToConstructWithCustomMethod()
    {
        $_SERVER['REQUEST_URI'] = '/documents';
        $_SERVER['REQUEST_METHOD'] = 'NOTPATCH';

        $request = new Request(new ServerRequest('PATCH', '/documents'));

        self::assertNotEquals(
            'NOTPATCH',
            $request->method,
            'Should ignore $_SERVER if method was passed on constructor'
        );
        self::assertEquals(
            'PATCH',
            $request->method,
            'Should use constructor method'
        );
    }

    /**
     * @covers  Respect\Rest\Request::__construct
     */
    public function testIsPossibleToConstructWithCustomUri()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/videos';

        $request = new Request(new ServerRequest('POST', '/images'));

        self::assertNotEquals(
            '/videos',
            $request->uri,
            'Should ignore $_SERVER if path was passed on constructor'
        );

        self::assertEquals(
            '/images',
            $request->uri,
            'Should use constructor path'
        );
    }

    /**
     * @covers      Respect\Rest\Request::__construct
     */
    public function testAbsoluteUrisShouldBeParsedToExtractThePathOnConstructor()
    {
        $_SERVER['REQUEST_URI'] = 'http://google.com/search?q=foo';

        $request = new Request(new ServerRequest('GET', 'http://google.com/search?q=foo'));

        self::assertNotEquals(
            'http://google.com/search?q=foo',
            $request->uri,
            'Absolute URI should not be on path'
        );

        self::assertEquals(
            '/search',
            $request->uri,
            'Path should be extracted from absolute URI'
        );
    }

    /**
     * @covers  Respect\Rest\Request::response
     */
    #[Depends('testIsPossibleToConstructUsingValuesFromSuperglobals')]
    public function testResponseIsNullWithoutSettingARoute(Request $request)
    {
        $response = $request->response();

        self::assertNull($response, 'Response should be null if no route is set');
    }

    /**
     * @covers  Respect\Rest\Request::response
     */
    #[Depends('testIsPossibleToConstructUsingValuesFromSuperglobals')]
    public function testRequestIsAbleToDeliverAResponseWithoutSettingPathParams(Request $request)
    {
        $request->route = $this->getMockForRoute(
            'GET',
            '/notebooks',
            ['Vaio', 'MacBook', 'ThinkPad']
        );
        $response = $request->response();

        self::assertNotNull($response, 'Response should not be null');
        self::assertEquals(
            '["Vaio","MacBook","ThinkPad"]',
            (string) $response->getBody(),
            'Response body should contain JSON-encoded array from runTarget'
        );
    }

    /**
     * @covers  Respect\Rest\Request::response
     */
    #[Depends('testIsPossibleToConstructUsingValuesFromSuperglobals')]
    public function testRequestIsAbleToDeliverAResponseUsingPreviouslySetPathParams(Request $request)
    {
        $request->route = $this->getMockForRoute(
            'GET',
            '/printers',
            'Some Printers Response',
            'GET',
            ['dpi', 'price']
        );
        $request->params = ['dpi', 'price'];
        $response = $request->response();

        self::assertEquals(
            'Some Printers Response',
            (string) $response->getBody(),
            'Response should return the route target when using previously set path params'
        );
    }

    /**
     * @covers  Respect\Rest\Request::forward
     */
    public function testForwardReplacesRouteAndReturnsResponse()
    {
        $request = new Request(new ServerRequest('GET', '/users/alganet/lists'));
        $inactiveRoute  = $this->getMockForRoute('GET', '/users/alganet/lists');
        $forwardedRoute = $this->getMockForRoute('GET', '/lists/12345', 'Some list items');
        $request->route = $inactiveRoute;
        $request->forward($forwardedRoute);

        self::assertNotSame(
            $inactiveRoute,
            $request->route,
            'After forwarding a route, the previous one should not be in the route attribute'
        );
        self::assertSame(
            $forwardedRoute,
            $request->route,
            'After forwarding a route, the forwarded route should be in the route attribute'
        );
    }

    /**
     * @covers       Respect\Rest\Request::response
     */
    #[Depends('testForwardReplacesRouteAndReturnsResponse')]
    #[DataProvider('providerForUserImplementedForwards')]
    public function testDeveloperCanForwardRoutesByReturningThemOnTheirImplementation(
        $scenario)
    {
        $internallyForwardedRoute = $this->getMockForRoute(
            'GET',
            '/candies/cupcakes',
            'Delicious Cupcake Internally Forwarded'
        );

        $forwardWithTarget = $this->getMockForRoute(
            'GET',
            '/cupcakes',
            function() use ($internallyForwardedRoute) {
                return $internallyForwardedRoute;
            }
        );

        $forwardWithByRoutine = $this->getMockForRoute(
            'GET',
            '/cereals',
            'Nice Cereals'
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

        $request = new Request(new ServerRequest('GET', '/cupcakes'));
        $request->route = $userImplementedRoute;
        $response = $request->response();

        self::assertSame('Delicious Cupcake Internally Forwarded', (string) $response->getBody());
    }

    public static function providerForUserImplementedForwards()
    {
        return [
            ['forwardWithTarget'],
            ['forwardWithByRoutine'],
        ];
    }

    /**
     * @covers Respect\Rest\Request::response
     */
    public function testDeveloperCanAbortRequestReturningFalseOnByRoutine()
    {
        $request = new Request(new ServerRequest('GET', '/protected-area'));
        $route = $this->getMockForRoute('GET', '/protected-area', 'Protected Content!!!');
        $routine = $this->getMockForRoutine('ProxyableBy');
        $routine->expects($this->once())
                ->method('by')
                ->willReturn(false);
        $route->appendRoutine($routine);
        $request->route = $route;

        $response = $request->response();

        self::assertNotSame(
            'Protected Content!!!',
            (string) $response->getBody(),
            'Response should not be the protected content.'
        );
        self::assertInstanceOf(
            \Psr\Http\Message\ResponseInterface::class,
            $response,
            'Response should be a ResponseInterface when aborting response.'
        );
        self::assertEmpty(
            (string) $response->getBody(),
            'Response body should be empty when aborting response.'
        );
    }

    /**
     * @covers Respect\Rest\Request::response
     */
    public function testDeveloperCanReturnCallablesToProcessOutputAfterTargetRuns()
    {
        $request = new Request(new ServerRequest('GET', '/logs'));
        $route = $this->getMockForRoute(
            'GET',
            '/logs',
            'user-deleted-something',
            'GET',
            $expectedParams = []
        );
        $routine = $this->getMockForRoutine('ProxyableThrough');
        $routine->expects($this->once())
                ->method('through')
                ->willReturn(function($thatLogStubReturnedAbove) {
                    return str_replace('-', ' ', $thatLogStubReturnedAbove);
                });
        $route->appendRoutine($routine);
        $request->route = $route;
        $response = $request->response();

        self::assertSame(
            'user deleted something',
            (string) $response->getBody(),
            "We passed a callback that replaced - for spaces, response should be passed to it."
        );
    }

    /**
     * @covers       Respect\Rest\Request::response
     */
    #[DataProvider('providerForParamSyncedRoutines')]
    public function testParamSyncedRoutinesShouldAllReferenceTheSameValuesByTheirNames(
        $scenario, array $params)
    {
        $request = new Request(new ServerRequest('GET', '/version'));
        $request->params = $params;

        $route = $this->getMockForRoute(
            'GET',
            '/version',
            'MySoftwareName',
            'GET',
            $params
        );

        $checkers = [];
        switch ($scenario) {
            case 'pureSynced':
                $checkers = [
                    function($majorVersion, $minorVersion, $patchVersion) {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertSame(5, $patchVersion);
                    },
                    function($patchVersion, $minorVersion, $majorVersion) {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertSame(5, $patchVersion);
                    },
                    function($majorVersion) {
                        self::assertCount(1, func_get_args());
                        self::assertSame(15, $majorVersion);
                    },
                    function() {
                        self::assertCount(0, func_get_args());
                    },
                ];
                break;
            case 'pureNulls':
                $checkers = [
                    function($majorVersion, $minorVersion, $patchVersion) {
                        self::assertCount(3, func_get_args());
                        self::assertNull($majorVersion);
                        self::assertNull($minorVersion);
                        self::assertNull($patchVersion);
                    },
                    function($patchVersion, $minorVersion, $majorVersion) {
                        self::assertCount(3, func_get_args());
                        self::assertNull($majorVersion);
                        self::assertNull($minorVersion);
                        self::assertNull($patchVersion);
                    },
                    function($patchVersion, $minorVersion, $majorVersion) {
                        self::assertCount(3, func_get_args());
                        self::assertNull($majorVersion);
                        self::assertNull($minorVersion);
                        self::assertNull($patchVersion);
                    },
                    function($majorVersion) {
                        self::assertCount(1, func_get_args());
                        self::assertNull($majorVersion);
                    },
                ];
                break;
            case 'pureDefaults':
                $checkers = [
                    function($majorVersion=15, $minorVersion=10, $patchVersion=5) {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertSame(5, $patchVersion);
                    },
                    function($patchVersion=5, $minorVersion=10, $majorVersion=15) {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertSame(5, $patchVersion);
                    },
                    function($majorVersion=15) {
                        self::assertCount(1, func_get_args());
                        self::assertSame(15, $majorVersion);
                    },
                    function() {
                        self::assertCount(0, func_get_args());
                    },
                ];
                break;
            case 'mixed':
                $checkers = [
                    function($majorVersion, $minorVersion, $patchVersion=5) {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertSame(5, $patchVersion);
                    },
                    function($majorVersion, $minorVersion, $patchVersion) {
                        self::assertCount(3, func_get_args());
                        self::assertSame(15, $majorVersion);
                        self::assertSame(10, $minorVersion);
                        self::assertNull($patchVersion);
                    },
                    function() {
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

        $request->route = $route;

        $response = $request->response();

        self::assertEquals('MySoftwareName', (string) $response->getBody());
    }

    public static function providerForParamSyncedRoutines()
    {
        return [
            ['pureSynced', [15, 10, 5]],
            ['pureNulls', []],
            ['pureDefaults', []],
            ['mixed', [15, 10]]
        ];
    }

    public function testConvertingToStringCallsResponse()
    {
        $request = new Request(new ServerRequest('GET', '/users/alganet/lists'));
        $request->route = $this->getMockForRoute('GET', '/users/alganet/lists', 'Some list items');
        $toString = (string) $request;

        self::assertSame('Some list items', $toString);
    }

    public function test_unsynced_param_comes_as_null()
    {
        $request = new Request(new ServerRequest('GET', '/'));
        $request->route = new Routes\Callback('GET', '/', function($bar) {
            return 'ok';
        });
        $args = [];
        $request->route->appendRoutine($routine = new Routines\By(function($foo, $bar, $baz) use (&$args){
            $args = func_get_args();
        }));
        $dummy = ['bar'];
        $request->routineCall('by', 'GET', $routine, $dummy);
        self::assertEquals([null, 'bar', null], $args);
    }

    protected function getMockForProxyableRoutine($route, $name, $implementation)
    {
        $routine = $this->getMockForRoutine(["Proxyable$name", "ParamSynced"]);
        $reflection = new ReflectionFunction($implementation);
        $route->expects($this->any())
          ->method('getReflection')
          ->with('GET')
          ->willReturn($reflection);
        $routine->expects($this->any())
                ->method('getParameters')
                ->willReturn($reflection->getParameters());
        $routine->expects($this->any())
          ->method(strtolower($name))
          ->willReturnCallback(function($request, $params) use ($implementation) {
              return $implementation(...$params);
          });

        return $routine;
    }

    protected function getMockForRoute($method, $pattern, $target = null,
        $targetMethod = 'GET', $targetParams = [])
    {
        $hasTarget = $target !== null;

        $route = $this->getMockBuilder('Respect\Rest\Routes\AbstractRoute')
            ->setConstructorArgs([$method, $pattern])
            ->onlyMethods(['getReflection', 'runTarget'])
            ->getMock();

        $route->responseFactory = new Psr17Factory();

        if ($hasTarget) {
            if ($targetParams) {
                $expectation = $route->expects($this->any())
                      ->method('runTarget')
                      ->with($targetMethod, $targetParams);
            } else {
                $expectation = $route->expects($this->any())
                      ->method('runTarget');
            }

            if (is_callable($target)){
                $expectation->willReturnCallback($target);
            } else {
                $expectation->willReturn($target);
            }
        }

        return $route;
    }

    protected function getMockForRoutine($interfaceList)
    {
        $interfaceName = 'GeneratedInterface'.md5((string) rand());

        $interfaceList = (array) $interfaceList;
        array_walk($interfaceList, function(&$interfaceSuffix) {
            $interfaceSuffix = "Respect\Rest\Routines\\$interfaceSuffix";
        });
        $interfaceList[] = 'Respect\Rest\Routines\Routinable';
        $interfaceList = array_unique($interfaceList);
        $interfaceList = implode(',', $interfaceList);

        eval("interface $interfaceName extends $interfaceList{}");

        return $this->createMock($interfaceName);
    }
}
