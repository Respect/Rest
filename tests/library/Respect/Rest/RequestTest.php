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
     * @covers Respect\Rest\Request::response 
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
     * @covers Respect\Rest\Request::response 
     * @depends testIsPossibleToConstructUsingValuesFromSuperglobals 
     */
    public function testRequestIsAbleToDeliverAResponseWithoutSettingPathParams(Request $request)
    {
        $request->route = $this->getMockForAbstractClass(
            '\Respect\Rest\Routes\AbstractRoute', 
            array('GET', '/notebooks')
        );
        $request->route->expects($this->once())
                       ->method('runTarget')
                       ->with('GET', array())
                       ->will($this->returnValue(array('Vaio', 'MacBook', 'ThinkPad')));
        $response = $request->response();

        $this->assertEquals(
            array('Vaio', 'MacBook', 'ThinkPad'),
            $response,
            'Response should have data returned from runTarget'
        );
    }

    /** 
     * @covers Respect\Rest\Request::response 
     * @depends testIsPossibleToConstructUsingValuesFromSuperglobals 
     */
    public function testRequestIsAbleToDeliverAResponseUsingPreviouslySetPathParams(Request $request)
    {
        $request->params = array('dpi', 'price');
        $request->route = $this->getMockForAbstractClass(
            '\Respect\Rest\Routes\AbstractRoute', 
            array('GET', '/printers')
        );
        $request->route->expects($this->once())
                       ->method('runTarget')
                       ->with('GET', array('dpi', 'price'))
                       ->will($this->returnValue(''));
        $response = $request->response();
    }
}