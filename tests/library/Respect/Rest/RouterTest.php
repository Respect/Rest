<?php

namespace Respect\Rest {

    class DummyRoute extends \DateTime implements Routable {}

    class NewRouterTest extends \PHPUnit_Framework_TestCase
    {
        function setUp()
        {
            $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
            $_SERVER['REQUEST_URI'] = '/';
            $_SERVER['REQUEST_METHOD'] = 'GET';
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
        function test_cleaning_up_params_should_remove_empty_string_params()
        {
            $params = array('foo', '', 'bar');
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

        function test_http_auth_should_send_401_and_WWW_headers_when_authentication_fails()
        {
            global $header;

            $auth = function($username, $password) {
                            return true;
                };
            $this->router->get('/', 'ok')->authBasic("Test Realm", $auth);
            $this->router->dispatch('get', '/')->response();
            $this->assertContains('HTTP/1.1 401', $header);
            $this->assertContains('WWW-Authenticate: Basic realm="Test Realm"', $header);
        }

        function test_http_auth_should_allow_redirects_inside_auth_closure()
        {
            global $header;

            $login = $this->router->get('/login', 'Login');
            $auth = function($username, $password) use($login) {
                        return $login;
                };
            $this->router->get('/', 'ok')->authBasic("Test Realm", $auth);
            $response = $this->router->dispatch('get', '/')->response();
            $this->assertEquals('Login', $response);
            $this->assertContains('HTTP/1.1 401', $header);
            $this->assertContains('WWW-Authenticate: Basic realm="Test Realm"', $header);
        }

        function test_auth_basic_request_should_be_aware_of_Authorization_headers()
        {
            global $header;
            $user           = 'John';
            $pass           = 'Doe';
            $checkpoint     = false;
            $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($user.':'.$pass);
            $this->router->get('/', 'ok')->authBasic("Test Realm", function($username, $password) use (&$checkpoint, $user, $pass) {
                            if (($username == $user) && ($password == $pass)) {
                                $checkpoint = true;
                                return true;
                            }
                            return false;
                         });
            (string) $this->router->dispatch('GET', '/')->response();
            $this->assertTrue($checkpoint, 'Auth not run');
            $this->assertNotContains('HTTP/1.1 401', $header);
            $this->assertNotContains('WWW-Authenticate: Basic realm="Test Realm"', $header);
            unset($_SERVER['HTTP_AUTHORIZATION']);
        }

        function test_auth_basic_authorized_should_be_aware_of_PHP_env_auth_variables()
        {
            global $header;
            $user           = 'John';
            $pass           = 'Doe';
            $checkpoint     = false;
            $_SERVER['PHP_AUTH_USER'] = $user;
            $_SERVER['PHP_AUTH_PW']   = $pass;
            $this->router->get('/', 'ok')->authBasic("Test Realm", function($username, $password) use (&$checkpoint, $user, $pass) {
                            if (($username == $user) && ($password == $pass)) {
                                $checkpoint = true;
                                return true;
                            }
                            return false;
                         });
            (string) $this->router->dispatch('GET', '/')->response();
            $this->assertTrue($checkpoint, 'Auth not run');
            $this->assertNotContains('HTTP/1.1 401', $header);
            $this->assertNotContains('WWW-Authenticate: Basic realm="Test Realm"', $header);
            unset($_SERVER['PHP_AUTH_PW'], $_SERVER['PHP_AUTH_USER']);
        }

        function test_auth_basic_pass_all_parameters_to_routine()
        {
            global $header;
            $user = 'John';
            $pass = 'Doe';
            $param1 = 'parameterX';
            $param2 = 'parameterY';
            $checkpoint = false;
            $_SERVER['PHP_AUTH_USER'] = $user;
            $_SERVER['PHP_AUTH_PW'] = $pass;
            $this->router->get('/*/*', 'ok')->authBasic("Test Realm", function($username, $password, $p1, $p2) use (&$checkpoint, $user, $pass, $param1, $param2)
            {
                if (($p1 === $param1) && $p2 === $param2) {
                    $checkpoint = true;
                    return true;
                }
                return false;
            });
            (string)$this->router->dispatch('GET', "/$param1/$param2")->response();
            $this->assertTrue($checkpoint, 'Parameters passed incorrectly');
            unset($_SERVER['PHP_AUTH_PW'], $_SERVER['PHP_AUTH_USER']);
        }

        /**
         * @group issues
         * @ticket 49
         */
        function test_http_auth_should_send_401_and_WWW_headers_when_authBasic_returns_false()
        {
            global $header;
            $user = 'John';
            $pass = 'Doe';
            $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($user.':'.$pass);
            $this->router->get('/', 'ok')->authBasic('Test Realm', function($username, $password) {
                return (($username == 'user') && ($password == 'pass'));
            });
            (string) $this->router->dispatch('GET', '/')->response();
            $this->assertContains('HTTP/1.1 401', $header);
            $this->assertContains('WWW-Authenticate: Basic realm="Test Realm"', $header);
            unset($_SERVER['HTTP_AUTHORIZATION']);
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
        static function provider_content_type_extension()
        {
            return array(
                array('text/html','.html'),
                array('application/json','.json'),
                array('text/xml','.xml')
            );
        }
        /**
         * @dataProvider provider_content_type_extension
         * @ticket 44
         */
        function test_do_not_set_automatic_content_type_header_for_extensions($ctype, $ext)
        {
            global $header;
            $_SERVER['HTTP_ACCEPT'] = $ctype;
            $r = new Router();
            $r->get('/auto', '')->accept(array($ext=>'json_encode'));


            $r = $r->dispatch('get', '/auto'.$ext)->response();
            $this->assertEmpty($header);
        }

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
    }

    class RouteKnowsGet implements \Respect\Rest\Routable {
        public function get($param) {
            return "ok: $param";
        }
    }
    class RouteKnowsNothing implements \Respect\Rest\Routable {
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
