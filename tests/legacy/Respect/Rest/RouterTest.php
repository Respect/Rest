<?php
declare(strict_types=1);

namespace Respect\Rest {

    use Nyholm\Psr7\ServerRequest;
    use Nyholm\Psr7\Factory\Psr17Factory;
    use PHPUnit\Framework\Attributes\DataProvider;

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
     * @covers Respect\Rest\Routines\CallbackList
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
    final class NewRouterTest extends \PHPUnit\Framework\TestCase
    {
        private $expectedHeader;
        private $router;

        function setUp(): void
        {
            $this->router = new Router(new Psr17Factory());
            $this->router->isAutoDispatched = false;
            $this->router->methodOverriding = false;
        }
        public function tearDown(): void
        {
            global $header;
            $header = [];
        }
        function test_magic_call_should_throw_exception_with_just_one_arg()
        {
            self::expectException('InvalidArgumentException');
            $this->router->thisIsAnInvalidMagicCallWithOnlyOneArg('foo');
        }

        function test_magic_call_should_throw_exception_with_zero_args()
        {
            self::expectException('InvalidArgumentException');
            $this->router->thisIsAnInvalidMagicCallWithOnlyOneArg();
        }

        function test_magic_call_with_closure_should_create_callback_route()
        {
            $route = $this->router->thisIsAMagicCall('/some/path', function() {});
            self::assertInstanceOf('Respect\Rest\Routes\Callback', $route);
        }
        function test_magic_call_with_func_name_should_create_callback_route()
        {
            $route = $this->router->thisIsAMagicCall('/some/path', 'strlen');
            self::assertInstanceOf('Respect\Rest\Routes\Callback', $route);
        }
        function test_magic_call_with_object_instance_should_create_instance_route()
        {
            $route = $this->router->thisIsAMagicCall(
                '/some/path', new DummyRoute
            );
            self::assertInstanceOf('Respect\Rest\Routes\Instance', $route);
        }
        function test_magic_call_with_class_name_should_return_classname_route()
        {
            $route = $this->router->thisIsAMagicCall(
                '/some/path', 'DateTime'
            );
            self::assertInstanceOf('Respect\Rest\Routes\ClassName', $route);
        }
        function test_magic_call_with_class_callback_should_return_factory_route()
        {
            $route = $this->router->thisIsAMagicCall(
                '/some/path', 'DateTime', [new \Datetime, 'format']
            );
            self::assertInstanceOf('Respect\Rest\Routes\Factory', $route);
        }
        function test_magic_call_with_class_with_constructor_should_return_class_route()
        {
            $route = $this->router->thisIsAMagicCall(
                '/some/path', 'DateTime', ['2989374983']
            );
            self::assertInstanceOf('Respect\Rest\Routes\ClassName', $route);
        }
        function test_magic_call_with_some_static_value()
        {
            $route = $this->router->thisIsAMagicCall(
                '/some/path', ['foo']
            );
            self::assertInstanceOf('Respect\Rest\Routes\StaticValue', $route);
        }
        function test_destructor_does_not_auto_dispatch_after_manual_dispatch()
        {
            $this->router->get('/**', function(){ return 'ok'; });
            $this->router->isAutoDispatched = true;
            $this->router->dispatch(new ServerRequest('GET', '/anything'));
            ob_start();
            unset($this->router);
            $response = ob_get_clean();
            self::assertEquals('', $response);
        }
        function test_dispatch_non_existing_route()
        {
            $this->router->any('/', function() {});
            $response = $this->router->dispatch(new ServerRequest('get', '/my/name/is/hall'))->response();
            self::assertNull($response, 'No route matched — response should be null');
        }
        function test_method_not_allowed_header()
        {
            $this->router->get('/', function() { return 'ok'; });
            $this->router->put('/', function() { return 'ok'; });
            $response = $this->router->dispatch(new ServerRequest('delete', '/'))->response();
            self::assertNull($response, 'Method not allowed — route should be null');
        }
        function test_bad_request_header()
        {
            // When routine returns false — route doesn't match
            $this->router->get('/', function() { return 'ok'; })->when(function(){return false;});
            $response = $this->router->dispatch(new ServerRequest('get', '/'))->response();
            self::assertNull($response);
        }
        function test_method_not_allowed_header_with_conneg()
        {
            $this->router->get('/', function() { return 'ok'; })
                         ->accept(['text/html' => function($d) {return $d;}]);
            $response = $this->router->dispatch(new ServerRequest('delete', '/'))->response();
            self::assertNull($response, 'Method not allowed — route should be null');
        }
        function test_transparent_options_allow_methods()
        {
            $this->router->get('/', function() { return 'ok'; });
            $this->router->post('/', function() { return 'ok'; });
            $response = $this->router->dispatch(new ServerRequest('options', '/'))->response();
            // OPTIONS without an explicit OPTIONS handler returns null route
            self::assertNull($response);
        }
        function test_transparent_global_options_allow_methods()
        {
            $this->router->get('/', function() { return 'ok'; });
            $this->router->post('/', function() { return 'ok'; });
            $response = $this->router->dispatch(new ServerRequest('options', '*'))->response();
            self::assertNull($response);
        }
        /**
         * @ticket 45
         */
        function test_method_overriding()
        {
            $this->router->methodOverriding = true;
            $this->router->put('/', function() { return 'ok'; });
            $serverRequest = (new ServerRequest('POST', '/'))->withParsedBody(['_method' => 'PUT']);
            $response = $this->router->run(new Request($serverRequest));
            self::assertEquals('ok', (string) $response->getBody());
        }
        /**
         * @ticket 45
         */
        function test_invalid_method_overriding_with_get()
        {
            $this->router->methodOverriding = true;
            $this->router->put('/', function() { return 'ok'; });
            $serverRequest = (new ServerRequest('GET', '/'))->withParsedBody(['_method' => 'PUT']);
            $response = $this->router->run(new Request($serverRequest));
            // GET requests should not allow method overriding (only POST)
            self::assertNull($response);
        }
        function test_method_not_acceptable()
        {
            // No Accept header → negotiation declines → 406 response
            $this->router->get('/', function() { return 'ok'; })
                         ->accept(['foo/bar' => function($d) {return $d;}]);
            $response = $this->router->dispatch(new ServerRequest('get', '/'))->response();
            self::assertNotNull($response);
            self::assertEquals(406, $response->getStatusCode());
        }
        function test_append_routine_honours_routine_chaining()
        {
            $this->router->get('/one-time', function() { return "one-time"; })
                ->appendRoutine(new Routines\Through(function ($data) {return function ($data) { return "$data-through1";};}))
                ->through(function ($data) {return function ($data) {return "$data-through2";};});
            $response = $this->router->dispatch(new ServerRequest('GET', '/one-time'));
            self::assertEquals('one-time-through1-through2', $response);
        }
        function test_callback_gets_param_array()
        {
            $this->router->get('/one-time/*', function($frag, $param1, $param2) {
                return "one-time-$frag-$param1-$param2";
            }, ['addl','add2']);
            $response = $this->router->dispatch(new ServerRequest('GET', '/one-time/1'));
            self::assertEquals('one-time-1-addl-add2', $response);
        }
        function test_http_method_head()
        {
            global $header;
            $expectedHeader = 'X-Burger: With Cheese!';
            $this->router->get('/', function() use ($expectedHeader) {
                header($expectedHeader);
                return 'ok';
            });
            $headResponse = $this->router->dispatch(new ServerRequest('HEAD', '/'));
            $getResponse  = $this->router->dispatch(new ServerRequest('GET', '/'));
            self::assertEquals('ok', (string) $getResponse);
            self::assertContains($expectedHeader, $header);
        }
        function test_http_method_head_with_classes_and_routines()
        {
            global $header;
            $expectedHeader = 'X-Burger: With Cheese!';
            $this->router->get('/', __NAMESPACE__.'\\HeadTest', [$expectedHeader])
                         ->when(function(){return true;});
            $headResponse = $this->router->dispatch(new ServerRequest('HEAD', '/'));
            $getResponse  = $this->router->dispatch(new ServerRequest('GET', '/'));
            self::assertEquals('ok', (string) $getResponse->response()->getBody());
            self::assertContains($expectedHeader, $header);
        }
        function test_user_agent_class()
        {
            $u = new KnowsUserAgent(['*' => function () {
        //        print_r(\func_get_args());
            }]);

            self::assertFalse($u->knowsCompareItems('a','b'));
            self::assertFalse($u->knowsCompareItems('c','b'));
            self::assertTrue($u->knowsCompareItems(1,'1'));
            self::assertTrue($u->knowsCompareItems('0',''));
            self::assertTrue($u->knowsCompareItems('a','*'));
        }

        function test_user_agent_content_negotiation()
        {
            $this->router->get('/', function () {
                return 'unknown';
            })->userAgent([
                'FIREFOX' => function() { return 'FIREFOX'; },
                'IE' => function() { return 'IE'; },
            ]);
            $serverRequest = (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX');
            $response = $this->router->dispatch($serverRequest);
            self::assertEquals('FIREFOX', $response);
        }
        function test_user_agent_content_negotiation_fallback()
        {
            $this->router->get('/', function () {
                return 'unknown';
            })->userAgent([
                '*' => function() { return 'IE'; },
            ]);
            $serverRequest = (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX');
            $response = $this->router->dispatch($serverRequest);
            self::assertEquals('IE', $response);
        }
        function test_stream_routine()
        {
            $done                            = false;
            $self                            = $this;
            $serverRequest                   = (new ServerRequest('GET', '/input'))
                                                ->withHeader('Accept-Encoding', 'deflate');
            $request                         = new Request($serverRequest);
            $this->router->get('/input', function() { return fopen('php://input', 'r+'); })
                         ->acceptEncoding([
                            'deflate' => function($stream) use ($self, &$done) {
                                $done = true;
                                $self->assertIsResource($stream);
                                stream_filter_append($stream, 'zlib.deflate', STREAM_FILTER_READ);
                                return $stream; //now deflated on demand
                            }
                         ]);

            $response = $this->router->run($request);
            self::assertTrue($done);
            self::assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
        }


        /**
         * @group issues
         * @ticket 37
        **/
        function test_optional_parameter_in_class_routes(){
            $r = new Router(new Psr17Factory());
            $r->any('/optional/*', __NAMESPACE__.'\\MyOptionalParamRoute');
            $response = $r->dispatch(new ServerRequest('get', '/optional'))->response();
            self::assertEquals('John Doe', (string) $response->getBody());
        }

        function test_optional_parameter_in_function_routes(){
            $r = new Router(new Psr17Factory());
            $r->any('/optional/*', function($user=null){
                return $user ?: 'John Doe';
            });
            $response = $r->dispatch(new ServerRequest('get', '/optional'))->response();
            self::assertEquals('John Doe', (string) $response->getBody());
        }

        function test_optional_parameter_in_function_routes_multiple(){
            $r = new Router(new Psr17Factory());
            $r->any('/optional', function(){
                return 'No User';
            });
            $r->any('/optional/*', function($user=null){
                return $user ?: 'John Doe';
            });
            $response = $r->dispatch(new ServerRequest('get', '/optional'))->response();
            self::assertEquals('No User', (string) $response->getBody());
        }
        function test_two_optional_parameters_in_function_routes(){
            $r = new Router(new Psr17Factory());
            $r->any('/optional/*/*', function($user=null, $list=null){
                return $user . $list;
            });
            $response = $r->dispatch(new ServerRequest('get', '/optional/Foo/Bar'))->response();
            self::assertEquals('FooBar', (string) $response->getBody());
        }
        function test_two_optional_parameters_one_passed_in_function_routes(){
            $r = new Router(new Psr17Factory());
            $r->any('/optional/*/*', function($user=null, $list=null){
                return $user . $list;
            });
            $response = $r->dispatch(new ServerRequest('get', '/optional/Foo'))->response();
            self::assertEquals('Foo', (string) $response->getBody());
        }
        function test_single_last_param()
        {
            $r = new Router(new Psr17Factory());
            $args = [];
            $r->any('/documents/*', function($documentId) use (&$args) {
                $args = func_get_args();
            });
            $r->dispatch(new ServerRequest('get', '/documents/1234'))->response();
            self::assertEquals(['1234'], $args);
        }
        function test_single_last_param2()
        {
            $r = new Router(new Psr17Factory());
            $args = [];
            $r->any('/documents/**', function($documentsPath) use (&$args) {
                $args = func_get_args();
            });
            $r->dispatch(new ServerRequest('get', '/documents/foo/bar'))->response();
            self::assertEquals([['foo', 'bar']], $args);
        }
        /**
         * Unit test for Commit: 3e8d536
         *
         * Catchall route called with / only would not get passed a parameter
         * to the callback function.
         */
        function test_catchall_on_root_call_should_get_callback_parameter()
        {
            $r = new Router(new Psr17Factory());
            $args = [];
            $r->any('/**', function($documentsPath) use (&$args) {
                $args = func_get_args();
            });
            $r->dispatch(new ServerRequest('get', '/'))->response();
            self::assertIsArray($args[0]);
        }

        /**
         * @ticket 46
         */
        public function test_is_callable_proxy()
        {
            $f = new Foo();
            $e = 'Hello';
            $r = new Router(new Psr17Factory());
            $r->get('/', $e)
              ->accept([
                'text/html' => [$f, 'getBar']
              ]);
            $serverRequest = (new ServerRequest('get', '/'))->withHeader('Accept', 'text/html');
            $response = $r->dispatch($serverRequest)->response();
            self::assertEquals($e, (string) $response->getBody());
        }

        static function provider_content_type()
        {
            return [
                ['text/html'],
                ['application/json']
            ];
        }
        /**
         * @ticket 44
         */
        #[DataProvider('provider_content_type')]
        function test_automatic_content_type_header($ctype)
        {
            $r = new Router(new Psr17Factory());
            $r->get('/auto', '')->accept([$ctype=>'json_encode']);
            $serverRequest = (new ServerRequest('get', '/auto'))->withHeader('Accept', $ctype);
            $response = $r->dispatch($serverRequest)->response();
            self::assertNotNull($response);
            self::assertEquals($ctype, $response->getHeaderLine('Content-Type'));
        }
        /**
         * @ticket 44
         */
        #[DataProvider('provider_content_type')]
        function test_wildcard_automatic_content_type_header($ctype)
        {
            $r = new Router(new Psr17Factory());
            $r->get('/auto', '')->accept([$ctype=>'json_encode']);
            $serverRequest = (new ServerRequest('get', '/auto'))->withHeader('Accept', '*/*');
            $response = $r->dispatch($serverRequest)->response();
            self::assertNotNull($response);
            self::assertEquals($ctype, $response->getHeaderLine('Content-Type'));
        }
        function test_request_forward()
        {
            $r = new Router(new Psr17Factory());
            $r1 = $r->get('/route1', 'route1');
            $response = (string) $r->dispatch(new ServerRequest('get', '/route1'))->response()->getBody();
            self::assertEquals('route1',$response);
            $r2 = $r->get('/route2', 'route2');
            $response = (string) $r->dispatch(new ServerRequest('get', '/route2'))->response()->getBody();
            self::assertEquals('route2',$response);
            $r2->by(function() use ($r1) { return $r1;});
            $response = (string) $r->dispatch(new ServerRequest('get', '/route2'))->response()->getBody();
            self::assertEquals('route1',$response);
        }
        static function provider_content_type_extension()
        {
            return [
                ['text/html','.html'],
                ['application/json','.json'],
                ['text/xml','.xml']
            ];
        }
        function test_negotiate_acceptable_complete_headers()
        {
            $this->router->get('/accept', function() { return 'ok'; })
                         ->accept(['foo/bar' => function($d) {return $d;}])
                         ->acceptLanguage(['13375p34|<' => function($d) {return $d;}]);
            $serverRequest = (new ServerRequest('get', '/accept'))
                ->withHeader('Accept', 'foo/bar')
                ->withHeader('Accept-Language', '13375p34|<');
            $response = $this->router->dispatch($serverRequest)->response();
            self::assertNotNull($response);
            self::assertEquals('ok', (string) $response->getBody());
            self::assertEquals('foo/bar', $response->getHeaderLine('Content-Type'));
            self::assertStringContainsString('accept-language', $response->getHeaderLine('Vary'));
            self::assertEquals('/accept', $response->getHeaderLine('Content-Location'));
            self::assertNotEmpty($response->getHeaderLine('Expires'));
            self::assertNotEmpty($response->getHeaderLine('Cache-Control'));
        }
        function test_accept_content_type_header()
        {
            $this->router->get('/', function() { return 'ok'; })
                         ->accept(['foo/bar' => function($d) {return $d;}]);
            $serverRequest = (new ServerRequest('get', '/'))->withHeader('Accept', 'foo/bar');
            $response = $this->router->dispatch($serverRequest)->response();
            self::assertNotNull($response);
            self::assertEquals('foo/bar', $response->getHeaderLine('Content-Type'));
        }
        function test_accept_content_language_header()
        {
            $this->router->get('/', function() { return 'ok'; })
                         ->acceptLanguage(['13375p34|<' => function($d) {return $d;}]);
            $serverRequest = (new ServerRequest('get', '/'))->withHeader('Accept-Language', '13375p34|<');
            $response = $this->router->dispatch($serverRequest)->response();
            self::assertNotNull($response);
            self::assertStringContainsString('accept-language', $response->getHeaderLine('Vary'));
        }
        /**
         * @ticket 44
         */
        #[DataProvider('provider_content_type_extension')]
        function test_do_not_set_automatic_content_type_header_for_extensions($ctype, $ext)
        {
            global $header;
            $header = [];
            $_SERVER['HTTP_ACCEPT'] = $ctype;
            $r = new Router(new Psr17Factory());
            $r->get('/auto', '')->accept([$ext=>'json_encode']);


            $r = $r->dispatch(new ServerRequest('get', '/auto'.$ext))->response();
            self::assertEmpty($header);
        }

        /**
         * @covers \Respect\Rest\Routes\AbstractRoute
         */
        function test_optional_parameters_should_be_allowed_only_at_the_end_of_the_path()
        {
            $r = new Router(new Psr17Factory());
            $r->get('/users/*/photos/*', function($username, $photoId=null) {
                return 'match';
            });
            $psrResponse = $r->dispatch(new ServerRequest('get', '/users/photos'))->response();
            $response = $psrResponse !== null ? (string) $psrResponse->getBody() : '';
            self::assertNotEquals('match', $response);
        }
        function test_route_ordering_with_when()
        {

            $when = false;
            $r = new Router(new Psr17Factory());

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
            $response = (string) $r->dispatch(new ServerRequest('get', '/users/1'))->response()->getBody();

            self::assertTrue($when);
            self::assertEquals('user-1', $response);
        }
        function test_when_should_be_called_only_on_existent_methods()
        {
            $_SERVER['HTTP_ACCEPT'] = 'application/json';

            $router = new \Respect\Rest\Router(new Psr17Factory());
            $router->isAutoDispatched = false;

            $r1 = $router->any('/meow/*', __NAMESPACE__.'\\RouteKnowsGet');
            $r1->accept(['application/json' => 'json_encode']); // some routine inheriting from AbstractAccept

            $router->any('/moo/*', __NAMESPACE__.'\\RouteKnowsNothing');

            $serverRequest = (new ServerRequest('get', '/meow/blub'))->withHeader('Accept', 'application/json');
            $out = (string) $router->run(new \Respect\Rest\Request($serverRequest))->getBody(); // ReflectionException

            self::assertEquals('"ok: blub"', $out);

        }
        
        function test_request_should_be_available_from_router_after_dispatching()
        {
            $request = new \Respect\Rest\Request(new ServerRequest('get', '/foo'));
            $router = new \Respect\Rest\Router(new Psr17Factory());
            $router->isAutoDispatched = false;
            $phpunit = $this;
            $router->get('/foo', function() use ($router, $request, $phpunit) {
                $phpunit->assertSame($request, $router->request);
                return spl_object_hash($router->request);
            });
            $out = (string) $router->run($request)->getBody();
            self::assertEquals($out, spl_object_hash($request));
        }

    }
    class RouteKnowsGet implements \Respect\Rest\Routable {
        public function get($param) {
            return "ok: $param";
        }
    }
    class RouteKnowsNothing implements \Respect\Rest\Routable {
    }

    class KnowsUserAgent {
        private Routines\UserAgent $userAgent;

        public function __construct(array $list) {
            $this->userAgent = new Routines\UserAgent($list);
        }

        public function knowsCompareItems($requested, $provided) {
            $ref = new \ReflectionMethod($this->userAgent, 'authorize');
            return $ref->invoke($this->userAgent, $requested, $provided);
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
            private $expectedHeader;
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
    $header=[];
}
