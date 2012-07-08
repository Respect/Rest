<?php
namespace Respect\Rest;

use Exception;
use PHPUnit_Framework_TestCase;

/** 
 * @covers Respect\Rest\Request 
 */
class RequestTest extends PHPUnit_Framework_TestCase
{
    /** 
     * @covers Respect\Rest\Request::__construct 
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
     * @covers Respect\Rest\Request::__construct 
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
     * @covers Respect\Rest\Request::__construct 
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
     * @covers Respect\Rest\Request::__construct 
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
            array('Vaio', 'MacBook', 'ThinkPad'), 
            'GET',
            array()
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
     * @covers Respect\Rest\Request::forward
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
     * @covers  Respect\Rest\Request::response
     * @depends testForwardReplacesRouteAndReturnsResponse 
     */
    public function testDeveloperCanForwardRoutesByReturningThemOnTheirImplementation()
    {
        $internallyForwardedRoute = $this->getMockForRoute(
            'GET', 
            '/candies/cupcakes', 
            'Delicious Cupcake Internally Forwarded', 
            'GET', 
            array()
        );
        $userImplementedRoute = $this->getMockForRoute(
            'GET', 
            '/cupcakes', 
            function() use($internallyForwardedRoute) {
                return $internallyForwardedRoute;
            },
            'GET', 
            array()
        );
        $request = new Request('GET', '/cupcakes');
        $request->route = $userImplementedRoute;
        $response = $request->response();
        
        $this->assertSame('Delicious Cupcake Internally Forwarded', $response);
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
            'user-deleted-something', 
            'GET', 
            $expectedParams = array()
        );
        $routine = $this->getMockForRoutine(array(
            'Respect\Rest\Routines\ProxyableThrough', 
            'Respect\Rest\Routines\Routinable'
        ));
        $routine->expects($this->once())
                ->method('through')
                ->with($request, $expectedParams)
                ->will($this->returnValue(function($thatLogStubReturnedAbove) {
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
        $targetMethod = 'GET', $tatgetParams = array())
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

            $route->expects($this->once())
                  ->method('runTarget')
                  ->with($targetMethod, $tatgetParams)
                  ->will($performAction);
        }

        return $route;
    }

    protected function getMockForRoutine($interfaceList)
    {
        $interfaceName = is_array($interfaceList) 
            ? 'GeneratedInterface'.md5(rand()) 
            : $interfaceList;

        $interfaceList = implode(',', (array) $interfaceList);
        
        eval("interface $interfaceName extends $interfaceList{}");
        $routine = $this->getMockForAbstractClass($interfaceName);

        return $routine;
    }
}