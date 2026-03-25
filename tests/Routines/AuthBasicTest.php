<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\Router;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\AuthBasic;
use Respect\Rest\Test\Stubs\DummyRoutine;

use function base64_encode;
use function class_alias;
use function func_get_args;

/** @covers Respect\Rest\Routines\AuthBasic */
final class AuthBasicTest extends TestCase
{
    private Router $router;

    /** @var array<int, mixed> */
    private static array $wantedParams = [];

    protected function setUp(): void
    {
        $this->router = new Router('', new Psr17Factory());
    }

    public function shunt_wantedParams(): void
    {
        self::assertSame(
            self::$wantedParams,
            func_get_args(),
            'wrong arguments were passed to the routine\'s callback',
        );
    }

    /** @covers Respect\Rest\Routes\AbstractRoute::appendRoutine */
    public function test_pass_all_params_to_callback(): void
    {
        $user = 'John';
        $pass = 'Doe';
        $param1 = 'abc';
        $param2 = 'def';
        self::$wantedParams = [$user, $pass, $param1, $param2];

        $serverRequest = (new ServerRequest('GET', '/' . $param1 . '/' . $param2))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $pass));
        $factory = new Psr17Factory();
        $context = new DispatchContext($serverRequest, $factory);
        $context->configureRoute($this->createRouteWithResponseFactory());

        $routine = new AuthBasic('auth realm', [$this, 'shunt_wantedParams']);
        $routine->by($context, [$param1, $param2]);
    }

    public function test_http_auth_should_send_401_and_WWW_headers_when_authentication_fails(): void
    {
        $auth = static function ($username, $password) {
                        return true;
        };
        $this->router->get('/', 'ok')->authBasic('Test Realm', $auth);
        $response = $this->router->dispatch(new ServerRequest('get', '/'))->response();
        self::assertNotNull($response);
        self::assertEquals(401, $response->getStatusCode());
        self::assertEquals('Basic realm="Test Realm"', $response->getHeaderLine('WWW-Authenticate'));
    }

    public function test_auth_basic_request_should_be_aware_of_Authorization_headers(): void
    {
        $user           = 'John';
        $pass           = 'Doe';
        $checkpoint     = false;
        $serverRequest = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $pass));
        $this->router->get('/', 'ok')->authBasic(
            'Test Realm',
            static function ($username, $password) use (&$checkpoint, $user, $pass) {
                if (($username == $user) && ($password == $pass)) {
                    $checkpoint = true;

                    return true;
                }

                return false;
            },
        );
        $response = $this->router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertTrue($checkpoint, 'Auth not run');
        self::assertNotEquals(401, $response->getStatusCode());
    }

    public function test_auth_basic_authorized_via_authorization_header(): void
    {
        $user           = 'John';
        $pass           = 'Doe';
        $checkpoint     = false;
        $serverRequest = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $pass));
        $this->router->get('/', 'ok')->authBasic(
            'Test Realm',
            static function ($username, $password) use (&$checkpoint, $user, $pass) {
                if (($username == $user) && ($password == $pass)) {
                    $checkpoint = true;

                    return true;
                }

                return false;
            },
        );
        $response = $this->router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertTrue($checkpoint, 'Auth not run');
        self::assertNotEquals(401, $response->getStatusCode());
    }

    public function test_auth_basic_pass_all_parameters_to_routine(): void
    {
        $user = 'John';
        $pass = 'Doe';
        $param1 = 'parameterX';
        $param2 = 'parameterY';
        $checkpoint = false;
        $serverRequest = (new ServerRequest('GET', '/' . $param1 . '/' . $param2))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $pass));
        $this->router->get('/*/*', 'ok')->authBasic(
            'Test Realm',
            static function ($username, $password, $p1 = null, $p2 = null) use (&$checkpoint, $param1, $param2) {
                if ($username === null && $password === null) {
                    return 'Unauthorized';
                }

                if (($p1 === $param1) && $p2 === $param2) {
                    $checkpoint = true;

                    return true;
                }

                return false;
            },
        );
        $resp = $this->router->dispatch($serverRequest)->response();
        self::assertNotNull($resp);
        (string) $resp->getBody();
        self::assertTrue($checkpoint, 'Parameters passed incorrectly');
    }

    /**
     * @covers Respect\Rest\Routines\AuthBasic::by
     * @group issues
     * @ticket 49
     */
    public function test_http_auth_should_send_401_and_WWW_headers_when_authBasic_returns_false(): void
    {
        $user = 'John';
        $pass = 'Doe';
        $serverRequest = (new ServerRequest('GET', '/'))
            ->withHeader('Authorization', 'Basic ' . base64_encode($user . ':' . $pass));
        $this->router->get('/', 'ok')->authBasic('Test Realm', static function ($username, $password) {
            return ($username == 'user') && ($password == 'pass');
        });
        $response = $this->router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertEquals(401, $response->getStatusCode());
        self::assertEquals('Basic realm="Test Realm"', $response->getHeaderLine('WWW-Authenticate'));
    }

    /** @covers Respect\Rest\Router::always */
    public function test_always_can_take_multiple_parameters_for_routine_constructor(): void
    {
        // Router::always() resolves routines by name under Respect\Rest\Routines\
        class_alias(DummyRoutine::class, 'Respect\Rest\Routines\DummyRoutine');
        DummyRoutine::$result = '';
        self::assertEmpty(DummyRoutine::$result);
        $r3 = new Router('', new Psr17Factory());
        $r3->always('dummyRoutine', 'arg1', 'arg2', 'arg3');
        self::assertEquals('arg1, arg2, arg3', DummyRoutine::$result);
    }

    private function createRouteWithResponseFactory(): AbstractRoute
    {
        return $this->createStub(AbstractRoute::class);
    }
}
