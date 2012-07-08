<?php

namespace Respect\Rest\Routines {

/**
 * @covers Respect\Rest\Routines\AuthBasic
 */
use Respect\Rest\Router;

class AuthBasicTest extends \PHPUnit_Framework_TestCase {

    private static $wantedParams;
    private $router;

    public function shunt_wantedParams()
    {
        $this->assertSame(self::$wantedParams, func_get_args(), 'wrong arguments were passed to the routine\'s callback');
    }

    function setUp()
    {
        global $header;
        $header = array();
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['CONTENT_TYPE'] = 'text/html';
        $_REQUEST['_method'] = '';
        $this->router = new Router;
        $this->router->isAutoDispatched = false;
        $this->router->methodOverriding = false;
    }

    protected function tearDown()
    {
        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
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
        self::$wantedParams = array($user, $pass, $param1, $param2);


        $_SERVER['PHP_AUTH_USER'] = $user;
        $_SERVER['PHP_AUTH_PW'] = $pass;
        unset($_SERVER['HTTP_AUTHORIZATION']);

        // to be able to run assertions in callback, I'm using a method here instead of a closure
        $routine = new AuthBasic('auth realm', array($this, 'shunt_wantedParams'));
        $routine->by(new \Respect\Rest\Request('GET', "/$param1/$param2"), array($param1, $param2));

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($user . ':' . $pass);

        $routine = new AuthBasic('auth realm', array($this, 'shunt_wantedParams'));
        $routine->by(new \Respect\Rest\Request('GET', "/$param1/$param2"), array($param1, $param2));
    }

    /*
     *  @covers Respect\Rest\Routines\AuthBasic::by
     */
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

    /**
     *  @covers Respect\Rest\Routines\AuthBasic::by
     */
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

    /**
     *  @covers Respect\Rest\Routines\AuthBasic::by
     */
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

    /**
     *  @covers Respect\Rest\Routines\AuthBasic::by
     */
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

    /**
     *  @covers Respect\Rest\Routines\AuthBasic::by
     */
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
     * @covers Respect\Rest\Routines\AuthBasic::by
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
     * @covers Respect\Rest\Router::always
     * #64
     */
    public function test_always_can_take_multiple_parameters_for_routine_constructor() {
        $this->assertEmpty(DummyRoutine::$result);
        $r3 = new \Respect\Rest\Router();
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
    $header=array();
}
