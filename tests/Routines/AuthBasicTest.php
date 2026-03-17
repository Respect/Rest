<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Request;
use Respect\Rest\Router;
use Respect\Rest\Routines\AuthBasic;
use Respect\Rest\Test\Stubs\DummyRoutine;

/**
 * @covers Respect\Rest\Routines\AuthBasic
 */
final class AuthBasicTest extends TestCase
{
    private static $wantedParams;
    private $router;

    public function shunt_wantedParams()
    {
        self::assertSame(self::$wantedParams, func_get_args(), 'wrong arguments were passed to the routine\'s callback');
    }

    protected function setUp(): void
    {
        $this->router = new Router(new Psr17Factory());
        $this->router->isAutoDispatched = false;
        $this->router->methodOverriding = false;
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

        $serverRequest = (new ServerRequest('GET', "/$param1/$param2"))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $pass));
        $request = new Request($serverRequest);
        $request->route = $this->createRouteWithResponseFactory();

        $routine = new AuthBasic('auth realm', [$this, 'shunt_wantedParams']);
        $routine->by($request, [$param1, $param2]);
    }

    private function createRouteWithResponseFactory()
    {
        $route = $this->createStub(\Respect\Rest\Routes\AbstractRoute::class);
        $route->responseFactory = new Psr17Factory();
        return $route;
    }

    public function test_http_auth_should_send_401_and_WWW_headers_when_authentication_fails()
    {
        $auth = function($username, $password) {
                        return true;
            };
        $this->router->get('/', 'ok')->authBasic("Test Realm", $auth);
        $response = $this->router->dispatch(new ServerRequest('get', '/'))->response();
        self::assertEquals(401, $response->getStatusCode());
        self::assertEquals('Basic realm="Test Realm"', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function test_http_auth_should_return_401_with_body_on_failure()
    {
        $auth = function($username, $password) {
                    if ($username === null && $password === null) {
                        return 'Login';
                    }
                    return true;
            };
        $this->router->get('/', 'ok')->authBasic("Test Realm", $auth);
        $response = $this->router->dispatch(new ServerRequest('get', '/'))->response();
        self::assertEquals(401, $response->getStatusCode());
        self::assertEquals('Login', (string) $response->getBody());
        self::assertEquals('Basic realm="Test Realm"', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function test_auth_basic_request_should_be_aware_of_Authorization_headers()
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
        self::assertTrue($checkpoint, 'Auth not run');
        self::assertNotEquals(401, $response->getStatusCode());
    }

    public function test_auth_basic_authorized_via_authorization_header()
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
        self::assertTrue($checkpoint, 'Auth not run');
        self::assertNotEquals(401, $response->getStatusCode());
    }

    public function test_auth_basic_pass_all_parameters_to_routine()
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
        self::assertTrue($checkpoint, 'Parameters passed incorrectly');
    }

    /**
     * @covers Respect\Rest\Routines\AuthBasic::by
     * @group issues
     * @ticket 49
     */
    public function test_http_auth_should_send_401_and_WWW_headers_when_authBasic_returns_false()
    {
        $user = 'John';
        $pass = 'Doe';
        $serverRequest = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user.':'.$pass));
        $this->router->get('/', 'ok')->authBasic('Test Realm', function($username, $password) {
            return (($username == 'user') && ($password == 'pass'));
        });
        $response = $this->router->dispatch($serverRequest)->response();
        self::assertEquals(401, $response->getStatusCode());
        self::assertEquals('Basic realm="Test Realm"', $response->getHeaderLine('WWW-Authenticate'));
    }

    /**
     * @covers Respect\Rest\Router::always
     */
    public function test_always_can_take_multiple_parameters_for_routine_constructor()
    {
        // Router::always() resolves routines by name under Respect\Rest\Routines\
        class_alias(DummyRoutine::class, 'Respect\Rest\Routines\DummyRoutine');
        DummyRoutine::$result = '';
        self::assertEmpty(DummyRoutine::$result);
        $r3 = new Router(new Psr17Factory());
        $r3->always('dummyRoutine', 'arg1', 'arg2', 'arg3');
        self::assertEquals('arg1, arg2, arg3', DummyRoutine::$result);
    }
}
