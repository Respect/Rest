<?php
namespace Respect\Rest;

use Exception;
use PHPUnit_Framework_TestCase;
use ReflectionFunction;

/** 
 * @covers Respect\Rest\Request 
 */
class RequestTest extends PHPUnit_Framework_TestCase
{
    /** 
     * @covers  Respect\Rest\Request::__construct
     */
    public function testIsPossibleToConstructUsingValuesFromSuperglobals()
    {
        $_SERVER['REQUEST_URI'] = '/users';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $request = new Request;

        $this->assertEquals(
            '/users', 
            $request->uri, 
            'Should inherit the path from $_SERVER'
        );
        $this->assertEquals(
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

        $request = new Request('PATCH');

        $this->assertNotEquals(
            'NOTPATCH', 
            $request->method,
            'Should ignore $_SERVER if method was passed on constructor'
        );
        $this->assertEquals(
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

        $request = new Request(null, '/images');

        $this->assertNotEquals(
            '/videos', 
            $request->uri,
            'Should ignore $_SERVER if path was passed on constructor'
        );

        $this->assertEquals(
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

        $request = new Request('GET');

        $this->assertNotEquals(
            'http://google.com/search?q=foo', 
            $request->uri,
            'Absolute URI should not be on path' //See TODO below
        );

        $this->assertEquals(
            '/search', 
            $request->uri,
            'Path should be extracted from absolute URI'
        );

        //TODO change ->uri to ->path, populate other parse_url keys
        //TODO same behavior for env vars and constructor params regarding parse_url
    }

    /** 
     * @covers  Respect\Rest\Request::response 
     * @depends testIsPossibleToConstructUsingValuesFromSuperglobals 
     */
    public function testResponseIsNullWithoutSettingARoute(Request $request)
    {
        $response = $request->response();

        $this->assertSame(
            null, 
            $response,
            'Response should be null if no route is set'
        );

        //TODO Request::response() should check if $this->route instanceof AbstractRoute
    }

    /** 
     * @covers  Respect\Rest\Request::response 
     * @depends testIsPossibleToConstructUsingValuesFromSuperglobals
     */
    public function testRequestIsAbleToDeliverAResponseWithoutSettingPathParams(Request $request)
    {
        $request->route = $this->getMockForRoute(
            'GET', 
            '/notebooks', 
            array('Vaio', 'MacBook', 'ThinkPad')
        );
        $response = $request->response();

        $this->assertEquals(
            array('Vaio', 'MacBook', 'ThinkPad'),
            $response,
            'Response should have data returned from runTarget'
        );
    }

    /** 
     * @covers  Respect\Rest\Request::response 
     * @depends testIsPossibleToConstructUsingValuesFromSuperglobals 
     */
    public function testRequestIsAbleToDeliverAResponseUsingPreviouslySetPathParams(Request $request)
    {
        $request->route = $this->getMockForRoute(
            'GET', 
            '/printers', 
            'Some Printers Response', 
            'GET',
            array('dpi', 'price')
        );
        $request->params = array('dpi', 'price');
        $response = $request->response();
    }

    /**
     * @covers  Respect\Rest\Request::forward
     * 
     */
    public function testForwardReplacesRouteAndReturnsResponse()
    {
        $request = $this->getMockForRequest('GET', '/users/alganet/lists', 'Some list items');
        $inactiveRoute  = $this->getMockForRoute('GET', '/users/alganet/lists');
        $forwardedRoute = $this->getMockForRoute('GET', '/lists/12345');
        $forwardedRoute->expects($this->never())
                       ->method('runTarget');
        $request->route = $inactiveRoute;
        $request->forward($forwardedRoute);

        $this->assertNotSame(
            $inactiveRoute, 
            $request->route,
            'After forwarding a route, the previous one should not be in the route attribute'
        );
        $this->assertSame(
            $forwardedRoute,
            $request->route,
            'After forwarding a route, the forwarded route should be in the route attribute'
        );
    }

    /**
     * @covers       Respect\Rest\Request::response
     * @depends      testForwardReplacesRouteAndReturnsResponse 
     * @dataProvider providerForUserImplementedForwards
     */
    public function testDeveloperCanForwardRoutesByReturningThemOnTheirImplementation(
        $userImplementedRoute)
    {
        $request = new Request('GET', '/cupcakes');
        $request->route = $userImplementedRoute;
        $response = $request->response();
        
        $this->assertSame('Delicious Cupcake Internally Forwarded', $response);
    }

    public function providerForUserImplementedForwards()
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
        $byRoutine = $this->getMockForRoutine('ProxyableBy');
        $byRoutine->expects($this->once())
                  ->method('by')
                  ->will($this->returnValue($internallyForwardedRoute));
        $forwardWithByRoutine->appendRoutine($byRoutine);
        return array(
            array($forwardWithTarget),
            array($forwardWithByRoutine)
        );
    }

    /**
     * @covers Respect\Rest\Request::response
     */
    public function testDeveloperCanAbortRequestReturningFalseOnByRoutine()
    {
        $request = new Request('GET', '/protected-area');
        $route = $this->getMockForRoute('GET', '/protected-area', 'Protected Content!!!');
        $routine = $this->getMockForRoutine('ProxyableBy');
        $routine->expects($this->once())
                ->method('by')
                ->will($this->returnValue(false));
        $route->appendRoutine($routine);
        $request->route = $route;

        $response = $request->response();

        $this->assertNotSame(
            'Protected Content!!!',
            $response,
            'Response should not be the protected content.'
        );
        $this->assertSame(
            false,
            $response,
            'Response should false when aborting response.'
        );
    }

    /**
     * @covers Respect\Rest\Request::response
     */
    public function testDeveloperCanReturnCallablesToProcessOutputAfterTargetRuns()
    {
        $request = new Request('GET', '/logs');
        $route = $this->getMockForRoute(
            'GET', 
            '/logs', 
            'user-deleted-something', // <-- Stub response, keep that in mind
            'GET', 
            $expectedParams = array()
        );
        $routine = $this->getMockForRoutine('ProxyableThrough');
        $routine->expects($this->once())
                ->method('through')
                ->will($this->returnValue(function($thatLogStubReturnedAbove) {
                    // Remember the stub response?
                    return str_replace('-', ' ', $thatLogStubReturnedAbove);
                }));
        $route->appendRoutine($routine);
        $request->route = $route;
        $response = $request->response();
        
        $this->assertSame(
            'user deleted something',
            $response,
            "We passed a callback that replaced - for spaces, response should be passed to it."
        );
    }

    /**
     * @covers       Respect\Rest\Request::response
     * @dataProvider providerForParamSyncedRoutines
     */
    public function testParamSyncedRoutinesShouldAllReferenceTheSameValuesByTheirNames(
        $checkers, array $params)
    {
        $request = new Request('GET', '/version');
        $request->params = $params;

        $route = $this->getMockForRoute(
            'GET', 
            '/version', 
            'MySoftwareName',
            'GET', 
            $params
        );
        foreach ($checkers as $checker) {
            $route->appendRoutine($this->getMockForProxyableRoutine($route, 'By', $checker));
        }

        $request->route = $route;

        $response = $request->response();

        $this->assertEquals('MySoftwareName', $response);
    }

    public function providerForParamSyncedRoutines()
    {
        $phpUnit = $this;
        $params = array(15, 10, 5);

        $pureSynced = array(
            function($majorVersion, $minorVersion, $patchVersion) use($phpUnit) {
                $phpUnit->assertCount(3, func_get_args());
                $phpUnit->assertSame(15, $majorVersion);
                $phpUnit->assertSame(10, $minorVersion);
                $phpUnit->assertSame(5, $patchVersion);
            },
            function($patchVersion, $minorVersion, $majorVersion) use($phpUnit) {
                $phpUnit->assertCount(3, func_get_args());
                $phpUnit->assertSame(15, $majorVersion);
                $phpUnit->assertSame(10, $minorVersion);
                $phpUnit->assertSame(5, $patchVersion);
            },
            function($majorVersion) use($phpUnit) {
                $phpUnit->assertCount(1, func_get_args());
                $phpUnit->assertSame(15, $majorVersion);
            },
            function() use($phpUnit) {
                $phpUnit->assertCount(0, func_get_args());
            },
        );

        $pureNulls = array(
            function($majorVersion, $minorVersion, $patchVersion) use($phpUnit) {
                $phpUnit->assertCount(3, func_get_args());
                $phpUnit->assertSame(null, $majorVersion);
                $phpUnit->assertSame(null, $minorVersion);
                $phpUnit->assertSame(null, $patchVersion);
            },
            function($patchVersion, $minorVersion, $majorVersion) use($phpUnit) {
                $phpUnit->assertCount(3, func_get_args());
                $phpUnit->assertSame(null, $majorVersion);
                $phpUnit->assertSame(null, $minorVersion);
                $phpUnit->assertSame(null, $patchVersion);
            },
            function($patchVersion, $minorVersion, $majorVersion) use($phpUnit) {
                $phpUnit->assertCount(3, func_get_args());
                $phpUnit->assertSame(null, $majorVersion);
                $phpUnit->assertSame(null, $minorVersion);
                $phpUnit->assertSame(null, $patchVersion);
            },
            function($majorVersion) use($phpUnit) {
                $phpUnit->assertCount(1, func_get_args());
                $phpUnit->assertSame(null, $majorVersion);
            },
            function() use($phpUnit) {
                $phpUnit->assertCount(0, func_get_args());
            },
        );

        $pureDefaults = array(
            function($majorVersion=15, $minorVersion=10, $patchVersion=5) use($phpUnit) {
                $phpUnit->assertCount(3, func_get_args());
                $phpUnit->assertSame(15, $majorVersion);
                $phpUnit->assertSame(10, $minorVersion);
                $phpUnit->assertSame(5, $patchVersion);
            },
            function($patchVersion=5, $minorVersion=10, $majorVersion=15) use($phpUnit) {
                $phpUnit->assertCount(3, func_get_args());
                $phpUnit->assertSame(15, $majorVersion);
                $phpUnit->assertSame(10, $minorVersion);
                $phpUnit->assertSame(5, $patchVersion);
            },
            function($majorVersion=15) use($phpUnit) {
                $phpUnit->assertCount(1, func_get_args());
                $phpUnit->assertSame(15, $majorVersion);
            },
            function() use($phpUnit) {
                $phpUnit->assertCount(0, func_get_args());
            },
        );

        $mixed = array(
            function($majorVersion, $minorVersion, $patchVersion=5) use($phpUnit) {
                $phpUnit->assertCount(3, func_get_args());
                $phpUnit->assertSame(15, $majorVersion);
                $phpUnit->assertSame(10, $minorVersion);
                $phpUnit->assertSame(5, $patchVersion);
            },
            function($majorVersion=15, $minorVersion, $patchVersion) use($phpUnit) {
                $phpUnit->assertCount(3, func_get_args());
                $phpUnit->assertSame(15, $majorVersion);
                $phpUnit->assertSame(10, $minorVersion);
                $phpUnit->assertSame(null, $patchVersion);
            },
            function() use($phpUnit) {
                $phpUnit->assertCount(0, func_get_args());
            },
        );

        return array(
            array($pureSynced, array(15, 10, 5)),
            array($pureNulls, array()),
            array($pureDefaults, array()),
            array($mixed, array(15, 10))
        );
    }

    public function testConvertingToStringCallsResponse() {
        $request = $this->getMockForRequest('GET', '/users/alganet/lists', 'Some list items');
        $toString = (string) $request;

        $this->assertSame('Some list items', $toString);
    }

    protected function getMockForProxyableRoutine($route, $name, $implementation)
    {
        $routine = $this->getMockForRoutine(array("Proxyable$name", "ParamSynced"));
        $route->expects($this->any())
          ->method('getReflection')
          ->with('GET')
          ->will($this->returnValue(
            $reflection = new ReflectionFunction($implementation)
          ));
        $routine->expects($this->any())
                ->method('getParameters')
                ->will($this->returnValue($reflection->getParameters()));
        $routine->expects($this->any())
          ->method(strtolower($name))
          ->will($this->returnCallback(function($request, $params) use ($implementation) {
              return call_user_func_array($implementation, $params);
          }));

        return $routine;
    }

    protected function getMockForRequest($method, $uri, $response=null)
    {
        $hasResponse = !is_null($response);
        $mockedMethods = array();

        if ($hasResponse) {
            $mockedMethods[] = 'response';
        }

        $constructorParams = array($method, $uri);

        $request = $this->getMock(
            'Respect\Rest\Request', 
            $mockedMethods, 
            $constructorParams
        );

        if ($hasResponse) {
            $request->expects($this->once())
                    ->method('response')
                    ->will($this->returnValue($response));
        }

        return $request;
    }

    protected function getMockForRoute($method, $pattern, $target = null, 
        $targetMethod = 'GET', $targetParams = array())
    {
        $hasTarget = !is_null($target);
        $mockedMethods = array();

        if ($hasTarget) {
            $mockedMethods[] = 'runTarget';
        }

        $constructorParams = array($method, $pattern);

        $route = $this->getMockForAbstractClass(
            'Respect\Rest\Routes\AbstractRoute',
            $constructorParams
        );

        if ($hasTarget) {
            if (is_callable($target)){
                $performAction = $this->returnCallback($target);
            } else {
                $performAction = $this->returnValue($target);
            }

            if ($targetParams) {
                $route->expects($this->any())
                      ->method('runTarget')
                      ->with($targetMethod, $targetParams)
                      ->will($performAction);
            } else {
                $route->expects($this->any())
                      ->method('runTarget')
                      ->will($performAction);
            }
        }

        return $route;
    }

    protected function getMockForRoutine($interfaceList)
    {
        $interfaceName = 'GeneratedInterface'.md5(rand());

        $interfaceList = (array) $interfaceList;
        array_walk($interfaceList, function(&$interfaceSuffix) {
            $interfaceSuffix = "Respect\Rest\Routines\\$interfaceSuffix";
        });
        $interfaceList[] = 'Respect\Rest\Routines\Routinable';
        $interfaceList = array_unique($interfaceList);
        $interfaceList = implode(',', $interfaceList);
        
        eval("interface $interfaceName extends $interfaceList{}");
        $routine = $this->getMockForAbstractClass($interfaceName);

        return $routine;
    }
}