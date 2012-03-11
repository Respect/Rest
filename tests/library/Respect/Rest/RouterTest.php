<?php

namespace Respect\Rest;

class DummyRoute extends \DateTime implements Routable {}

class NewRouterTest extends \PHPUnit_Framework_TestCase
{
    function setUp() 
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->router = new Router;
        $this->router->isAutoDispatched = false;
    }
    public function tearDown()
    {
        global $header;
        $header = array();
    }
    function test_cleaning_up_params_should_remove_first_param_always()
    {
        $params = array('/', 'foo', '', 'bar');
        $this->assertNotContains('/', Router::cleanUpParams($params));
    }
    function test_cleaning_up_params_should_remove_empty_string_params()
    {
        $params = array('/', 'foo', '', 'bar');
        $expected = array('foo', 'bar');
        $this->assertEquals($expected, Router::cleanUpParams($params));
    }
    function test_magic_call_should_throw_exception_with_just_one_arg()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->router->thisIsAnInvalidMagicCallWithOnlyOneArg('foo');
    }
    function test_magic_call_should_throw_exception_with_zero_args()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->router->thisIsAnInvalidMagicCallWithOnlyOneArg();
    }
    function test_magic_call_with_closure_should_create_callback_route()
    {
        $route = $this->router->thisIsAMagicCall('/some/path', function() {});
        $this->assertInstanceOf('Respect\Rest\Routes\Callback', $route); 
    }
    function test_magic_call_with_func_name_should_create_callback_route()
    {
        $route = $this->router->thisIsAMagicCall('/some/path', 'strlen');
        $this->assertInstanceOf('Respect\Rest\Routes\Callback', $route); 
    }
    function test_magic_call_with_object_instance_should_create_instance_route()
    { 
        $route = $this->router->thisIsAMagicCall(
            '/some/path', new DummyRoute
        );
        $this->assertInstanceOf('Respect\Rest\Routes\Instance', $route); 
    }
    function test_magic_call_with_class_name_should_return_classname_route()
    { 
        $route = $this->router->thisIsAMagicCall(
            '/some/path', 'DateTime'
        );
        $this->assertInstanceOf('Respect\Rest\Routes\ClassName', $route); 
    }
    function test_magic_call_with_class_callback_should_return_factory_route()
    { 
        $route = $this->router->thisIsAMagicCall(
            '/some/path', 'DateTime', array(new \Datetime, 'format')
        );
        $this->assertInstanceOf('Respect\Rest\Routes\Factory', $route); 
    }
    function test_magic_call_with_class_with_constructor_should_return_class_route()
    { 
        $route = $this->router->thisIsAMagicCall(
            '/some/path', 'DateTime', array('2989374983')
        );
        $this->assertInstanceOf('Respect\Rest\Routes\ClassName', $route); 
    }
    function test_magic_call_with_some_static_value()
    { 
        $route = $this->router->thisIsAMagicCall(
            '/some/path', array('foo')
        );
        $this->assertInstanceOf('Respect\Rest\Routes\StaticValue', $route); 
    }
    function test_destructor_runs_router_automatically_when_protocol_is_present()
    {
        $this->router->get('/**', function(){ return 'ok'; });
        $this->router->isAutoDispatched = true;
        ob_start();
        unset($this->router);
        $response = ob_get_clean();
        $this->assertEquals('ok', $response);
    }
    function test_converting_router_to_string_should_dispatch_and_run_it()
    {
        $this->router->get('/**', function(){ return 'ok'; });
        $response = (string) $this->router;
        $this->assertEquals('ok', $response);
    }
    function test_dispatch_non_existing_route()
    {
        global $header;
        $this->router->any('/', function() {});
        $this->router->dispatch('get', '/my/name/is/hall');
        $this->assertContains('HTTP/1.1 404', $header);
    }
    function test_method_not_allowed_header()
    {
        global $header;
        $this->router->get('/', function() { return 'ok'; });
        $this->router->put('/', function() { return 'ok'; });
        $this->router->dispatch('delete', '/');
        $this->assertContains('HTTP/1.1 405', $header);
        $this->assertContains('Allow: GET, PUT', $header);
    }
    function test_method_not_allowed_header_with_conneg()
    {
        global $header;
        $this->router->get('/', function() { return 'ok'; })
                     ->accept(array('text/html' => function($d) {return $d;}));
        $this->router->dispatch('delete', '/');
        $this->assertContains('HTTP/1.1 405', $header);
        $this->assertContains('Allow: GET', $header);
    }
    function test_http_method_head()
    {
        global $header;
        $expectedHeader = 'X-Burger: With Cheese!';
        $this->router->get('/', function() use ($expectedHeader) { 
            header($expectedHeader);
            return 'ok'; 
        });
        $headResponse = $this->router->dispatch('HEAD', '/');
        $getResponse  = $this->router->dispatch('GET', '/');
        $this->assertEquals('ok', (string) $getResponse);
        $this->assertContains($expectedHeader, $header);
    }
}
$header=array();
/**
 * header() stub to tests ...
 * @return void
 */
function header($string, $replace=true, $http_response_code=200)
{
    global $header;
    if (!$replace && isset($header))
        return;

    $header[$string] = $string;
}