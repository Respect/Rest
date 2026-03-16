<?php

namespace Respect\Rest\Routines {

/**
 * @covers Respect\Rest\Routines\AuthBasic
 */
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Respect\Rest\Router;

class AuthBasicTest extends \PHPUnit\Framework\TestCase {

    private static $wantedParams;
    private $router;

    public function shunt_wantedParams()
    {
        $this->assertSame(self::$wantedParams, func_get_args(), 'wrong arguments were passed to the routine\'s callback');
    }

    function setUp(): void
    {
        global $header;
        $header = [];
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['CONTENT_TYPE'] = 'text/html';
        $_REQUEST['_method'] = '';
        $this->router = new Router(new Psr17Factory());
        $this->router->isAutoDispatched = false;
        $this->router->methodOverriding = false;
    }

    protected function tearDown(): void
    {
    }

    /**
     * @covers Respect\Rest\Routes\AbstractRoute::appendRoutine
     */
    public function test_pass_all_params_to_callback()
    {

        $user = 'John';
        $pass = 'Doe';
        $param1 = 'abc';
        $param2 = 'def';
        self::$wantedParams = [$user, $pass, $param1, $param2];

        // AuthBasic now only reads from the Authorization header in the ServerRequest
        $serverRequest = (new ServerRequest('GET', "/$param1/$param2"))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $pass));
        $request = new \Respect\Rest\Request($serverRequest);
        $request->route = $this->createRouteWithResponseFactory();

        // to be able to run assertions in callback, I'm using a method here instead of a closure
        $routine = new AuthBasic('auth realm', [$this, 'shunt_wantedParams']);
        $routine->by($request, [$param1, $param2]);
    }

    private function createRouteWithResponseFactory()
    {
        $route = $this->getMockBuilder(\Respect\Rest\Routes\AbstractRoute::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getReflection', 'runTarget'])
            ->getMock();
        $route->responseFactory = new Psr17Factory();
        return $route;
    }

    /*
     *  @covers Respect\Rest\Routines\AuthBasic::by
     */
    function test_http_auth_should_send_401_and_WWW_headers_when_authentication_fails()
    {
        $auth = function($username, $password) {
                        return true;
            };
        $this->router->get('/', 'ok')->authBasic("Test Realm", $auth);
        // No Authorization header -> should get 401 response
        $response = $this->router->dispatch(new ServerRequest('get', '/'))->response();
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Basic realm="Test Realm"', $response->getHeaderLine('WWW-Authenticate'));
    }

    /**
     *  @covers Respect\Rest\Routines\AuthBasic::by
     *
     * With the modernized AuthBasic, on auth failure (no Authorization header),
     * a 401 ResponseInterface is returned. The callback is invoked with (null, null)
     * and its return value is written to the response body as a string.
     */
    function test_http_auth_should_return_401_with_body_on_failure()
    {
        $auth = function($username, $password) {
                    if ($username === null && $password === null) {
                        return 'Login';
                    }
                    return true;
            };
        $this->router->get('/', 'ok')->authBasic("Test Realm", $auth);
        $response = $this->router->dispatch(new ServerRequest('get', '/'))->response();
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Login', (string) $response->getBody());
        $this->assertEquals('Basic realm="Test Realm"', $response->getHeaderLine('WWW-Authenticate'));
    }

    /**
     *  @covers Respect\Rest\Routines\AuthBasic::by
     */
    function test_auth_basic_request_should_be_aware_of_Authorization_headers()
    {
        $user           = 'John';
        $pass           = 'Doe';
        $checkpoint     = false;
        $serverRequest = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user.':'.$pass));
        $this->router->get('/', 'ok')->authBasic("Test Realm", function($username, $password) use (&$checkpoint, $user, $pass) {
                        if (($username == $user) && ($password == $pass)) {
                            $checkpoint = true;
                            return true;
                        }
                        return false;
                     });
        $response = $this->router->dispatch($serverRequest)->response();
        $this->assertTrue($checkpoint, 'Auth not run');
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    /**
     *  @covers Respect\Rest\Routines\AuthBasic::by
     *
     * AuthBasic now only reads from the Authorization header (PSR-7).
     * The PHP_AUTH_USER/PW $_SERVER variables are no longer used directly.
     * This test verifies that credentials via the Authorization header work.
     */
    function test_auth_basic_authorized_via_authorization_header()
    {
        $user           = 'John';
        $pass           = 'Doe';
        $checkpoint     = false;
        $serverRequest = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user.':'.$pass));
        $this->router->get('/', 'ok')->authBasic("Test Realm", function($username, $password) use (&$checkpoint, $user, $pass) {
                        if (($username == $user) && ($password == $pass)) {
                            $checkpoint = true;
                            return true;
                        }
                        return false;
                     });
        $response = $this->router->dispatch($serverRequest)->response();
        $this->assertTrue($checkpoint, 'Auth not run');
        $this->assertNotEquals(401, $response->getStatusCode());
    }

    /**
     *  @covers Respect\Rest\Routines\AuthBasic::by
     */
    function test_auth_basic_pass_all_parameters_to_routine()
    {
        $user = 'John';
        $pass = 'Doe';
        $param1 = 'parameterX';
        $param2 = 'parameterY';
        $checkpoint = false;
        $serverRequest = (new ServerRequest('GET', "/$param1/$param2"))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user.':'.$pass));
        $this->router->get('/*/*', 'ok')->authBasic("Test Realm", function($username, $password, $p1=null, $p2=null) use (&$checkpoint, $user, $pass, $param1, $param2)
        {
            if ($username === null && $password === null) {
                return 'Unauthorized';
            }
            if (($p1 === $param1) && $p2 === $param2) {
                $checkpoint = true;
                return true;
            }
            return false;
        });
        (string)$this->router->dispatch($serverRequest)->response()->getBody();
        $this->assertTrue($checkpoint, 'Parameters passed incorrectly');
    }

    /**
     * @covers Respect\Rest\Routines\AuthBasic::by
     * @group issues
     * @ticket 49
     */
    function test_http_auth_should_send_401_and_WWW_headers_when_authBasic_returns_false()
    {
        $user = 'John';
        $pass = 'Doe';
        $serverRequest = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user.':'.$pass));
        $this->router->get('/', 'ok')->authBasic('Test Realm', function($username, $password) {
            return (($username == 'user') && ($password == 'pass'));
        });
        $response = $this->router->dispatch($serverRequest)->response();
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Basic realm="Test Realm"', $response->getHeaderLine('WWW-Authenticate'));
    }

    /**
     * @covers Respect\Rest\Router::always
     * #64
     */
    public function test_always_can_take_multiple_parameters_for_routine_constructor() {
        $this->assertEmpty(DummyRoutine::$result);
        $r3 = new \Respect\Rest\Router(new Psr17Factory());
        $r3->always('dummyRoutine', 'arg1', 'arg2', 'arg3');
        $this->assertEquals('arg1, arg2, arg3', DummyRoutine::$result);
    }
}
class DummyRoutine implements Routinable {
    public static $result = '';
    public function __construct($param1, $param2, $param3) {
        static::$result = "$param1, $param2, $param3";
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

namespace Respect\Rest {
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
