<?php

namespace Respect\Rest {

    class DummyRoute extends \DateTime implements Routable {}
    /**
     * @covers Respect\Rest\Router
     * @covers Respect\Rest\Request
     * @covers Respect\Rest\Routable
     * @covers Respect\Rest\Routes\AbstractRoute
     * @covers Respect\Rest\Routes\CallBack
     * @covers Respect\Rest\Routes\ClassName
     * @covers Respect\Rest\Routes\Factory
     * @covers Respect\Rest\Routes\Instance
     * @covers Respect\Rest\Routes\StaticValue
     * @covers Respect\Rest\Routines\AbstractAccept
     * @covers Respect\Rest\Routines\AbstractCallbackList
     * @covers Respect\Rest\Routines\AbstractCallbackMediator
     * @covers Respect\Rest\Routines\AbstractRoutine
     * @covers Respect\Rest\Routines\AbstractSyncedRoutine
     * @covers Respect\Rest\Routines\Accept
     * @covers Respect\Rest\Routines\AcceptCharset
     * @covers Respect\Rest\Routines\AcceptLanguage
     * @covers Respect\Rest\Routines\AcceptEncoding
     * @covers Respect\Rest\Routines\AuthBasic
     * @covers Respect\Rest\Routines\By
     * @covers Respect\Rest\Routines\ContentType
     * @covers Respect\Rest\Routines\IgnorableFileExtension
     * @covers Respect\Rest\Routines\ProxyableBy
     * @covers Respect\Rest\Routines\LastModified
     * @covers Respect\Rest\Routines\ParamSynced
     * @covers Respect\Rest\Routines\ProxyableBy
     * @covers Respect\Rest\Routines\ProxyableThrough
     * @covers Respect\Rest\Routines\ProxyableWhen
     * @covers Respect\Rest\Routines\Routinable
     * @covers Respect\Rest\Routines\Through
     * @covers Respect\Rest\Routines\Unique
     * @covers Respect\Rest\Routines\UserAgent
     * @covers Respect\Rest\Routines\When
     */
    class NewRouterTest extends \PHPUnit_Framework_TestCase
    {
        function setUp()
        {
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
            $_SERVER['REQUEST_URI'] = '/';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            $_SERVER['CONTENT_TYPE'] = 'text/html';
            $_REQUEST['_method'] = '';
            $this->router = new Router;
            $this->router->isAutoDispatched = false;
            $this->router->methodOverriding = false;
        }
        public function tearDown()
        {
            global $header;
            $header = array();
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
        function test_dispatch_non_existing_route()
        {
            global $header;
            $this->router->any('/', function() {});
            $this->router->dispatch('get', '/my/name/is/hall');
            $this->assertContains('HTTP/1.1 404', $header);
            $this->assertNotContains('HTTP/1.1 405', $header);
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
        function test_bad_request_header()
        {
            global $header;
            $this->router->get('/', function() { return 'ok'; })->when(function(){return false;});
            $this->router->dispatch('get', '/');
            $this->assertContains('HTTP/1.1 400', $header);
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
        function test_transparent_options_allow_methods()
        {
            global $header;
            $this->router->get('/', function() { return 'ok'; });
            $this->router->post('/', function() { return 'ok'; });
            $this->router->dispatch('options', '/');
            $this->assertNotContains('HTTP/1.1 405', $header);
            $this->assertContains('Allow: GET, POST', $header);
        }
        function test_transparent_global_options_allow_methods()
        {
            global $header;
            $this->router->get('/', function() { return 'ok'; });
            $this->router->post('/', function() { return 'ok'; });
            $this->router->dispatch('options', '*');
            $this->assertNotContains('HTTP/1.1 405', $header);
            $this->assertContains('Allow: GET, POST', $header);
        }
        /**
         * @ticket 45
         */
        function test_method_overriding()
        {
            $this->router->methodOverriding = true;
            $_REQUEST['_method'] = 'PUT';
            $this->router->put('/', function() { return 'ok'; });
            $response = $this->router->run(new Request('POST', '/'));
            $this->assertEquals('ok', (string) $response);
            $this->router->methodOverriding = false;
        }
        /**
         * @ticket 45
         */
        function test_invalid_method_overriding_with_get()
        {
            global $header;
            $this->router->methodOverriding = true;
            $_REQUEST['_method'] = 'PUT';
            $this->router->put('/', function() { return 'ok'; });
            $response = $this->router->run(new Request('GET', '/'));
            $this->assertNotEquals('ok', (string) $response);
            $this->assertContains('HTTP/1.1 405', $header);
            $this->router->methodOverriding = false;
        }
        function test_method_not_acceptable()
        {
            global $header;
            $this->router->get('/', function() { return 'ok'; })
                         ->accept(array('foo/bar' => function($d) {return $d;}));
            $this->router->dispatch('get', '/');
            $this->assertContains('HTTP/1.1 406', $header);
            $this->assertContains('Allow: GET', $header);
        }
        function test_append_routine_honours_routine_chaining()
        {
            $this->router->get('/one-time', function() { return "one-time"; })
                ->appendRoutine(new Routines\Through(function ($data) {return function ($data) { return "$data-through1";};}))
                ->through(function ($data) {return function ($data) {return "$data-through2";};});
            $response = $this->router->dispatch('GET', '/one-time');
            $this->assertEquals('one-time-through1-through2', $response);
        }
        function test_callback_gets_param_array()
        {
            $this->router->get('/one-time/*', function($frag, $param1, $param2) {
                return "one-time-$frag-$param1-$param2";
            }, array('addl','add2'));
            $response = $this->router->dispatch('GET', '/one-time/1');
            $this->assertEquals('one-time-1-addl-add2', $response);
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
        function test_http_method_head_with_classes_and_routines()
        {
            global $header;
            $expectedHeader = 'X-Burger: With Cheese!';
            $this->router->get('/', __NAMESPACE__.'\\HeadTest', array($expectedHeader))
                         ->when(function(){return true;});
            $headResponse = $this->router->dispatch('HEAD', '/');
            $getResponse  = $this->router->dispatch('GET', '/');
            $this->assertEquals('ok', $getResponse->response());
            $this->assertContains($expectedHeader, $header);
        }
        function test_user_agent_class()
        {
            $u = new KnowsUserAgent(array('*' => function () {
        //        print_r(\func_get_args());
            }));

            $this->assertFalse($u->knowsCompareItems('a','b'));
            $this->assertFalse($u->knowsCompareItems('c','b'));
            $this->assertTrue($u->knowsCompareItems(1,'1'));
            $this->assertTrue($u->knowsCompareItems('0',''));
            $this->assertTrue($u->knowsCompareItems('a','*'));
        }

        function test_user_agent_content_negotiation()
        {
            $_SERVER['HTTP_USER_AGENT'] = 'FIREFOX';
            $this->router->get('/', function () {
                return 'unknown';
            })->userAgent(array(
                'FIREFOX' => function() { return 'FIREFOX'; },
                'IE' => function() { return 'IE'; },
            ));
            $response = $this->router->dispatch('GET', '/');
            $this->assertEquals('FIREFOX', $response);
        }
        function test_user_agent_content_negotiation_fallback()
        {
            $_SERVER['HTTP_USER_AGENT'] = 'FIREFOX';
            $this->router->get('/', function () {
                return 'unknown';
            })->userAgent(array(
                '*' => function() { return 'IE'; },
            ));
            $response = $this->router->dispatch('GET', '/');
            $this->assertEquals('IE', $response);
        }
        function test_stream_routine()
        {
            $done                            = false;
            $self                            = $this;
            $request                         = new Request('GET', '/input');
            $_SERVER['HTTP_ACCEPT_ENCODING'] = 'deflate';
            $this->router->get('/input', function() { return fopen('php://input', 'r+'); })
                         ->acceptEncoding(array(
                            'deflate' => function($stream) use ($self, &$done) {
                                $done = true;
                                $self->assertTrue(is_resource($stream));
                                stream_filter_append($stream, 'zlib.deflate', STREAM_FILTER_READ);
                                return $stream; //now deflated on demand
                            }
                         ));

            $response = $this->router->run($request);
            $this->assertTrue($done);
            //var_dump((string)$response);
            $this->assertEmpty((string) $response);
        }


        /**
         * @group issues
         * @ticket 37
        **/
        function test_optional_parameter_in_class_routes(){
            $r = new Router();
            $r->any('/optional/*', __NAMESPACE__.'\\MyOptionalParamRoute');
            $response = $r->dispatch('get', '/optional')->response();
            $this->assertEquals('John Doe', (string) $response);
        }

        function test_optional_parameter_in_function_routes(){
            $r = new Router();
            $r->any('/optional/*', function($user=null){
                return $user ?: 'John Doe';
            });
            $response = $r->dispatch('get', '/optional')->response();
            $this->assertEquals('John Doe', (string) $response);
        }

        function test_optional_parameter_in_function_routes_multiple(){
            $r = new Router();
            $r->any('/optional', function(){
                return 'No User';
            });
            $r->any('/optional/*', function($user=null){
                return $user ?: 'John Doe';
            });
            $response = $r->dispatch('get', '/optional')->response();
            $this->assertEquals('No User', (string) $response);
        }
        function test_two_optional_parameters_in_function_routes(){
            $r = new Router();
            $r->any('/optional/*/*', function($user=null, $list=null){
                return $user . $list;
            });
            $response = $r->dispatch('get', '/optional/Foo/Bar')->response();
            $this->assertEquals('FooBar', (string) $response);
        }
        function test_two_optional_parameters_one_passed_in_function_routes(){
            $r = new Router();
            $r->any('/optional/*/*', function($user=null, $list=null){
                return $user . $list;
            });
            $response = $r->dispatch('get', '/optional/Foo')->response();
            $this->assertEquals('Foo', (string) $response);
        }
        function test_single_last_param()
        {
            $r = new Router();
            $args = array();
            $r->any('/documents/*', function($documentId) use (&$args) {
                $args = func_get_args();
            });
            $r->dispatch('get', '/documents/1234')->response();
            $this->assertEquals(array('1234'), $args);
        }
        function test_single_last_param2()
        {
            $r = new Router();
            $args = array();
            $r->any('/documents/**', function($documentsPath) use (&$args) {
                $args = func_get_args();
            });
            $r->dispatch('get', '/documents/foo/bar')->response();
            $this->assertEquals(array(array('foo', 'bar')), $args);
        }
        /**
         * Unit test for Commit: 3e8d536
         *
         * Catchall route called with / only would not get passed a parameter
         * to the callback function.
         */
        function test_catchall_on_root_call_should_get_callback_parameter()
        {
            $r = new Router();
            $args = array();
            $r->any('/**', function($documentsPath) use (&$args) {
                $args = func_get_args();
            });
            $r->dispatch('get', '/')->response();
            $this->assertTrue(\is_array($args[0]));
        }

        /**
         * @ticket 46
         */
        public function test_is_callable_proxy()
        {
            $_SERVER['HTTP_ACCEPT'] = 'text/html';
            $f = new Foo();
            $e = 'Hello';
            $r = new Router();
            $r->get('/', $e)
              ->accept(array(
                'text/html' => array($f, 'getBar')
              ));
            $response = $r->dispatch('get', '/')->response();
            $this->assertEquals($e, (string) $response);
        }

        static function provider_content_type()
        {
            return array(
                array('text/html'),
                array('application/json')
            );
        }
        /**
         * @dataProvider provider_content_type
         * @ticket 44
         */
        function test_automatic_content_type_header($ctype)
        {
            global $header;
            $_SERVER['HTTP_ACCEPT'] = $ctype;
            $r = new Router();
            $r->get('/auto', '')->accept(array($ctype=>'json_encode'));


            $r = $r->dispatch('get', '/auto')->response();
            $this->assertContains('Content-Type: '.$ctype, $header);
        }
        /**
         * @dataProvider provider_content_type
         * @ticket 44
         */
        function test_wildcard_automatic_content_type_header($ctype)
        {
            global $header;
            $_SERVER['HTTP_ACCEPT'] = '*/*';
            $r = new Router();
            $r->get('/auto', '')->accept(array($ctype=>'json_encode'));


            $r = $r->dispatch('get', '/auto')->response();
            $this->assertContains('Content-Type: '.$ctype, $header);
        }
        function test_request_forward()
        {
            $r = new Router();
            $r1 = $r->get('/route1', 'route1');
            $response = $r->dispatch('get', '/route1')->response();
            $this->assertEquals('route1',$response);
            $r2 = $r->get('/route2', 'route2');
            $response = $r->dispatch('get', '/route2')->response();
            $this->assertEquals('route2',$response);
            $r2->by(function() use ($r1) { return $r1;});
            $response = $r->dispatch('get', '/route2')->response();
            $this->assertEquals('route1',$response);
        }
        static function provider_content_type_extension()
        {
            return array(
                array('text/html','.html'),
                array('application/json','.json'),
                array('text/xml','.xml')
            );
        }
        function test_negotiate_acceptable_complete_headers()
        {
            global $header;
            $_SERVER['REQUEST_URI'] = '/accept';
            $_SERVER['HTTP_ACCEPT'] = 'foo/bar';
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '13375p34|<';
            $this->router->get('/accept', function() { return 'ok'; })
                         ->accept(array('foo/bar' => function($d) {return $d;}))
                         ->acceptLanguage(array('13375p34|<' => function($d) {return $d;}));
            $this->router->dispatch('get', '/accept');
            $this->assertContains('Content-Type: foo/bar', $header);
            $this->assertContains('Content-Language: 13375p34|<', $header);
            $this->assertRegExp('/Vary: negotiate,.*accept(?!-)/', implode("\n", $header));
            $this->assertRegExp('/Vary: negotiate,.*accept-language/', implode("\n", $header));
            $this->assertContains('Content-Location: /accept', $header);
            $this->assertContains('Expires: Thu, 01 Jan 1980 00:00:00 GMT', $header);
            $this->assertContains('Cache-Control: max-age=86400', $header);
        }
        function test_accept_content_type_header()
        {
            global $header;
            $_SERVER['HTTP_ACCEPT'] = 'foo/bar';
            $this->router->get('/', function() { return 'ok'; })
                         ->accept(array('foo/bar' => function($d) {return $d;}));
            $this->router->dispatch('get', '/');
            $this->assertContains('Content-Type: foo/bar', $header);
            $this->assertRegExp('/Vary: negotiate,.*accept(?!-)/', implode("\n", $header));
        }
        function test_accept_content_language_header()
        {
            global $header;
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '13375p34|<';
            $this->router->get('/', function() { return 'ok'; })
                         ->acceptLanguage(array('13375p34|<' => function($d) {return $d;}));
            $this->router->dispatch('get', '/');
            $this->assertContains('Content-Language: 13375p34|<', $header);
            $this->assertRegExp('/Vary: negotiate,.*accept-language/', implode("\n", $header));
        }
        /**
         * @dataProvider provider_content_type_extension
         * @ticket 44
         */
        function test_do_not_set_automatic_content_type_header_for_extensions($ctype, $ext)
        {
            global $header;
            $header = array();
            $_SERVER['HTTP_ACCEPT'] = $ctype;
            $r = new Router();
            $r->get('/auto', '')->accept(array($ext=>'json_encode'));


            $r = $r->dispatch('get', '/auto'.$ext)->response();
            $this->assertEmpty($header);
        }

        /**
         * @covers \Respect\Rest\Routes\AbstractRoute
         */
        function test_optional_parameters_should_be_allowed_only_at_the_end_of_the_path()
        {
            $r = new Router();
            $r->get('/users/*/photos/*', function($username, $photoId=null) {
                return 'match';
            });
            $response = $r->dispatch('get', '/users/photos')->response();
            $this->assertNotEquals('match', $response);
        }
        function test_route_ordering_with_when()
        {

            $when = false;
            $r = new Router();

            $r->get('/','HOME');

            $r->get('/users',function(){
                return 'users';
            });

            $r->get('/users/*',function($userId){
                return 'user-'.$userId;
            })->when(function($userId) use (&$when){
                $when = true;
                return is_numeric($userId) && $userId > 0;
            });

            $r->get('/docs', function() {return 'DOCS!';});
            $response = $r->dispatch('get', '/users/1')->response();

            $this->assertTrue($when);
            $this->assertEquals('user-1', $response);
        }
        function test_when_should_be_called_only_on_existent_methods()
        {
            $_SERVER['HTTP_ACCEPT'] = 'application/json';

            $router = new \Respect\Rest\Router();
            $router->isAutoDispatched = false;

            $r1 = $router->any('/meow/*', __NAMESPACE__.'\\RouteKnowsGet');
            $r1->accept(array('application/json' => 'json_encode')); // some routine inheriting from AbstractAccept

            $router->any('/moo/*', __NAMESPACE__.'\\RouteKnowsNothing');

            $out = (string) $router->run(new \Respect\Rest\Request('get', '/meow/blub')); // ReflectionException

            $this->assertEquals('"ok: blub"', $out);

        }
        
        function test_request_should_be_available_from_router_after_dispatching()
        {
            $request = new \Respect\Rest\Request('get', '/foo');
            $router = new \Respect\Rest\Router();
            $router->isAutoDispatched = false;
            $phpunit = $this;
            $router->get('/foo', function() use ($router, $request, $phpunit) {
                $phpunit->assertSame($request, $router->request);
                return spl_object_hash($router->request);
            });
            $out = $router->run($request);
            $this->assertEquals($out, spl_object_hash($request));
        }

    }
    class RouteKnowsGet implements \Respect\Rest\Routable {
        public function get($param) {
            return "ok: $param";
        }
    }
    class RouteKnowsNothing implements \Respect\Rest\Routable {
    }

    class KnowsUserAgent extends Routines\UserAgent {
        public function knowsCompareItems($requested, $provided) {
            return $this->authorize($requested, $provided);
        }
    }

    if (!class_exists(__NAMESPACE__.'\\MyOptionalParamRoute')) {
        class MyOptionalParamRoute implements Routable
        {

            public function get($user=null)
            {
                return $user ?: 'John Doe';
            }
        }
    }

    if (!class_exists(__NAMESPACE__.'\\Foo')) {
        class Foo
        {
            public function getBar($bar)
            {
                return $bar;
            }
        }
    }

    if (!class_exists(__NAMESPACE__.'\\HeadTest')) {
        class HeadTest implements Routable
        {
            public function __construct($expectedHeader)
            {
                $this->expectedHeader = $expectedHeader;
            }
            public function get()
            {
                header($this->expectedHeader);
                return 'ok';
            }
        }
    }


    if (!function_exists(__NAMESPACE__.'\\header')) {
        function header($string, $replace=true, $http_response_code=200)
        {
            global $header;
            if (!$replace && isset($header))
                return;

            $header[$string] = $string;
        }
    }
}

namespace Respect\Rest\Routines {
    if (!function_exists(__NAMESPACE__.'\\header')) {
        function header($string, $replace=true, $http_response_code=200)
        {
            global $header;
            if (!$replace && isset($header))
                return;

            $header[$string] = $string;
        }
    }
}

namespace {
    $header=array();
}
