<?php
declare(strict_types=1);

namespace Respect\Rest\Test;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Request;
use Respect\Rest\Routable;
use Respect\Rest\Router;
use Respect\Rest\Routines;
use Respect\Rest\Test\Stubs\DummyRoute;
use Respect\Rest\Test\Stubs\Foo;
use Respect\Rest\Test\Stubs\HeadTest as HeadTestStub;
use Respect\Rest\Test\Stubs\KnowsUserAgent;
use Respect\Rest\Test\Stubs\MyController;
use Respect\Rest\Test\Stubs\MyOptionalParamRoute;
use Respect\Rest\Test\Stubs\RouteKnowsGet;
use Respect\Rest\Test\Stubs\RouteKnowsNothing;
use Respect\Rest\Test\Stubs\StubRoutable;

/**
 * @covers Respect\Rest\Router
 */
final class RouterTest extends TestCase
{
    protected $router;
    protected $result;
    protected $callback;

    protected function setUp(): void
    {
        $this->router = new Router(new Psr17Factory());
        $this->result = null;
        $result = &$this->result;
        $this->callback = function() use(&$result) {
                $result = func_get_args();
            };
    }

    // =========================================================================
    // Library RouterTest methods
    // =========================================================================

    /**
     * @covers Respect\Rest\Router::__call
     */
    public function testMagicConstructorWarnsIfNoSufficientParametersWerePassed()
    {
        self::expectException('InvalidArgumentException');
        $router = new Router(new Psr17Factory());
        $router->thisIsInsufficientForMagicConstruction();
    }

    /**
     * @covers Respect\Rest\Router::__call
     */
    public function testMagicConstructorWarnsIfNoSufficientParametersWerePassed2()
    {
        self::expectException('InvalidArgumentException');
        $router = new Router(new Psr17Factory());
        $router->thisIsInsufficientForMagicConstruction('/magicians');
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::callbackRoute
     */
    public function testMagicConstructorCanCreateCallbackRoutes()
    {
        $router = new Router(new Psr17Factory());
        $callbackRoute = $router->get('/', $target = function() {});
        $concreteCallbackRoute = $router->callbackRoute('GET', '/', $target);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\Callback',
            $callbackRoute,
            'Returned result from a magic constructor in this case should return a Routes\Callback'
        );

        self::assertEmpty(
            $callbackRoute->arguments,
            'When there are no arguments the Routes\Callback should have none as well'
        );

        self::assertEquals(
            $callbackRoute,
            $concreteCallbackRoute,
            'The magic and concrete instances of Routes\Callback should be equivalent'
        );
    }

    /**
     * @covers  Respect\Rest\Router::__call
     * @covers  Respect\Rest\Router::callbackRoute
     * @depends testMagicConstructorCanCreateCallbackRoutes
     */
    public function testMagicConstructorCanCreateCallbackRoutesWithExtraParams()
    {
        $router = new Router(new Psr17Factory());
        $callbackRoute = $router->get('/', $target = function() {}, ['extra']);
        $concreteCallbackRoute = $router->callbackRoute('GET', '/', $target, ['extra']);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\Callback',
            $callbackRoute,
            'Returned result from a magic constructor in this case should return a Routes\Callback'
        );

        self::assertContains(
            'extra',
            $callbackRoute->arguments,
            'The "extra" appended to the magic constructor should be present on the arguments list'
        );

        self::assertEquals(
            $callbackRoute,
            $concreteCallbackRoute,
            'The magic and concrete instances of Routes\Callback should be equivalent'
        );
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::instanceRoute
     */
    public function testMagicConstructorCanRouteToPreBuiltInstances()
    {
        $router = new Router(new Psr17Factory());
        $myInstance = new class implements Routable {
            public function GET() { return 'mock response'; }
        };
        $instanceRoute = $router->get('/', $myInstance);
        $concreteInstanceRoute = $router->instanceRoute('GET', '/', $myInstance);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\Instance',
            $instanceRoute,
            'Returned result from a magic constructor in this case should return a Routes\Instance'
        );

        self::assertEquals(
            $instanceRoute,
            $concreteInstanceRoute,
            'The magic and concrete instances of Routes\Instance should be equivalent'
        );
    }

    /**
     * @covers       Respect\Rest\Router::__call
     * @covers       Respect\Rest\Router::staticRoute
     */
    #[DataProvider('provideForStaticRoutableValues')]
    public function testMagicConstructorCanRouteToStaticValue($staticValue, $reason)
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $staticRoute = $router->get('/', $staticValue);
        $concreteStaticRoute = $router->staticRoute('GET','/', $staticValue);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\StaticValue',
            $staticRoute,
            $reason
        );

        self::assertEquals(
            $staticRoute,
            $concreteStaticRoute,
            'The magic and concrete instances of Routes\Static should be equivalent'
        );
    }

    public static function provideForStaticRoutableValues()
    {
        return [
            ['Some Static Value', 'Strings should be possible to route statically'],
            [['Some', 'Other', 'Routable', 'Value'], 'Arrays should be possible to route statically'],
            [10, 'Integers and scalars should be possible to route statically']
        ];
    }

    /**
     * @covers            Respect\Rest\Router::__call
     * @covers            Respect\Rest\Router::staticRoute
     */
    #[DataProvider('provideForNonStaticRoutableValues')]
    public function testMagicConstructorCannotRouteSomeStaticValues($staticValue, $reason)
    {
        self::expectException(\InvalidArgumentException::class);
        $router = new Router(new Psr17Factory());
        $nonStaticRoute = $router->get('/', $staticValue);

        $router->run(new Request(new ServerRequest('GET', '/')));

        self::assertNotInstanceOf(
            'Respect\\Rest\\Routes\\StaticValue',
            $nonStaticRoute,
            $reason
        );
    }

    public static function provideForNonStaticRoutableValues()
    {
        return [
            ['PDO', 'Strings that are class names should NOT be possible to route statically'],
            ['Traversable', 'Strings that are interface names should NOT be possible to route statically']
        ];
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::classRoute
     */
    public function testMagicConstructorCanRouteToClasses()
    {
        $router = new Router(new Psr17Factory());
        $className = StubRoutable::class;
        $classRoute = $router->get('/', $className);
        $concreteClassRoute = $router->classRoute('GET', '/', $className);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\ClassName',
            $classRoute,
            'Returned result from a magic constructor in this case should return a Routes\ClassName'
        );

        self::assertEquals(
            $classRoute,
            $concreteClassRoute,
            'The magic and concrete instances of Routes\ClassName should be equivalent'
        );
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::classRoute
     */
    public function testMagicConstructorCanRouteToClassesUsingConstructorParams()
    {
        $router = new Router(new Psr17Factory());
        $className = StubRoutable::class;
        $classRoute = $router->get('/', $className, ['some', 'constructor', 'params']);
        $concreteClassRoute = $router->classRoute('GET', '/', $className, ['some', 'constructor', 'params']);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\ClassName',
            $classRoute,
            'Returned result from a magic constructor in this case should return a Routes\ClassName'
        );

        self::assertEquals(
            ['some', 'constructor', 'params'],
            $classRoute->constructorParams,
            'The constructor params should be available on the instance of Routes\ClassName'
        );

        self::assertEquals(
            $classRoute,
            $concreteClassRoute,
            'The magic and concrete instances of Routes\ClassName should be equivalent'
        );
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::factoryRoute
     */
    public function testMagicConstructorCanRouteToFactoriesThatReturnInstancesOfAClass()
    {
        $router = new Router(new Psr17Factory());
        eval('class MockRoutable2 implements Respect\Rest\Routable{ public function GET() {} }');
        eval('class FactoryClass2 { public static function factoryMethod() { return new MockRoutable2(); } }');
        $factoryRoute = $router->get('/', 'FactoryClass2', ['FactoryClass2', 'factoryMethod']);
        $concreteFactoryRoute = $router->factoryRoute('GET', '/', 'FactoryClass2', ['FactoryClass2', 'factoryMethod']);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\Factory',
            $factoryRoute,
            'Returned result from a magic constructor in this case should return a Routes\Factory'
        );

        self::assertEquals(
            $factoryRoute,
            $concreteFactoryRoute,
            'The magic and concrete instances of Routes\Factory should be equivalent'
        );
    }

    /**
     * @covers Respect\Rest\Router::dispatchRequest
     * @covers Respect\Rest\Router::isRoutelessDispatch
     * @covers Respect\Rest\Router::isDispatchedToGlobalOptionsMethod
     * @covers Respect\Rest\Router::getAllowedMethods
     * @runInSeparateProcess
     */
    public function testCanRespondToGlobalOptionsMethodAutomatically()
    {
        $router = new Router(new Psr17Factory());
        $router->get('/asian', 'Asian Food!');
        $router->post('/eastern', 'Eastern Food!');
        $router->eat('/mongolian', 'Mongolian Food!');
        $response = $router->dispatch(new ServerRequest('OPTIONS', '*'))->response();

        self::assertNull($response);
    }

    /**
     * @covers Respect\Rest\Router::dispatchRequest
     * @covers Respect\Rest\Router::isRoutelessDispatch
     * @covers Respect\Rest\Router::hasDispatchedOverridenMethod
     */
    public function testDeveloperCanOverridePostMethodWithQueryStringParameter()
    {
        $router = new Router(new Psr17Factory());
        $router->methodOverriding = true;
        $router->put('/bulbs', 'Some Bulbs Put Response');
        $router->post('/bulbs', 'Some Bulbs Post Response');

        $serverRequest = (new ServerRequest('POST', '/bulbs'))->withParsedBody(['_method' => 'PUT']);
        $result = (string) $router->dispatch($serverRequest)->response()->getBody();

        self::assertSame(
            'Some Bulbs Put Response',
            $result,
            'Router should dispatch to PUT (overriden) instead of POST'
        );

        self::assertNotSame(
            'Some Bulbs Post Response',
            $result,
            'Router NOT dispatch to POST when method is overriden'
        );

        return $router;
    }

    /**
     * @covers  Respect\Rest\Router::dispatchRequest
     * @covers  Respect\Rest\Router::isRoutelessDispatch
     * @covers  Respect\Rest\Router::hasDispatchedOverridenMethod
     */
    #[Depends('testDeveloperCanOverridePostMethodWithQueryStringParameter')]
    public function testDeveloperCanTurnOffMethodOverriding(Router $router)
    {
        $router->methodOverriding = false;
        $serverRequest = (new ServerRequest('POST', '/bulbs'))->withParsedBody(['_method' => 'PUT']);
        $result = (string) $router->dispatch($serverRequest)->response()->getBody();

        self::assertSame(
            'Some Bulbs Post Response',
            $result,
            'Router should dispatch to POST (not overriden) instead of PUT'
        );

        self::assertNotSame(
            'Some Bulbs Put Response',
            $result,
            'Router NOT dispatch to PUT when method is overriden'
        );
    }

    /**
     * @covers  Respect\Rest\Router::dispatchRequest
     * @covers  Respect\Rest\Router::routeDispatch
     * @covers  Respect\Rest\Router::applyVirtualHost
     */
    public function testDeveloperCanSetUpAVirtualHostPathOnConstructor()
    {
        $router = new Router(new Psr17Factory(), '/store');
        $router->get('/products', 'Some Products!');
        $response = (string) $router->dispatch(new ServerRequest('GET', '/store/products'))->response()->getBody();

        self::assertSame(
            'Some Products!',
            $response,
            'Router should match using the virtual host combined URI'
        );
    }

    /**
     * @covers Respect\Rest\Router::__destruct
     */
    public function testRouterDoesNotAutoDispatchAfterManualDispatch()
    {
        $router = new Router(new Psr17Factory());
        $router->get('/', 'Hello Respect');
        $router->dispatch(new ServerRequest('GET', '/'));
        unset($router);

        self::expectOutputString('');
    }

    /**
     * @covers Respect\Rest\Router::dispatch
     * @covers Respect\Rest\Router::routeDispatch
     */
    public function testReturns404WhenNoRoutesExist()
    {
        $router = new Router(new Psr17Factory());
        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNull($response, 'No routes — response should be null (404)');
    }

    /**
     * @covers Respect\Rest\Router::dispatch
     * @covers Respect\Rest\Router::routeDispatch
     */
    public function testReturns404WhenNoRouteMatches()
    {
        $router = new Router(new Psr17Factory());
        $router->get('/foo', 'This exists.');
        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNull($response, 'No route matched — response should be null (404)');
    }

    /**
     * @covers Respect\Rest\Router::appendRoute
     */
    public function testNamesRoutesUsingAttributes()
    {
        $router = new Router(new Psr17Factory());
        $router->allMembers = $router->any('/members', 'John, Carl');
        $response = (string) $router->dispatch(new ServerRequest('GET', '/members'))->response()->getBody();

        $ref = new \ReflectionObject($router);
        self::assertTrue($ref->hasProperty('allMembers'), 'There must be an attribute set for that key');

        self::assertEquals(
            'John, Carl',
            $response,
            'The route must be declared anyway'
        );
    }

    /**
     * @covers Respect\Rest\Router::applyVirtualHost
     * @covers Respect\Rest\Router::appendRoute
     */
    public function testCreateUriShouldBeAwareOfVirtualHost()
    {
        $router = new Router(new Psr17Factory(), '/my/virtual/host');
        $catsRoute = $router->any('/cats/*', 'Meow');
        $virtualHostUri = $catsRoute->createUri('mittens');
        self::assertEquals(
            '/my/virtual/host/cats/mittens',
            $virtualHostUri,
            'Virtual host should be prepended to the path on createUri()'
        );
    }

    /**
     * @covers Respect\Rest\Router::handleOptionsRequest
     * @runInSeparateProcess
     */
    public function testOptionsRequestShouldNotCallOtherHandlers()
    {
        $router = new Router(new Psr17Factory());
        $router->get('/asian', 'GET: Asian Food!');
        $router->post('/asian', 'POST: Asian Food!');

        $response = $router->dispatch(new ServerRequest('OPTIONS', '/asian'))->response();

        self::assertNull(
            $response,
            'OPTIONS request should not call any of the other registered handlers.'
        );
    }

    /**
     * @covers Respect\Rest\Router::handleOptionsRequest
     */
    public function testOptionsRequestShouldBeDispatchedToCorrectOptionsHandler()
    {
        $router = new Router(new Psr17Factory());
        $router->get('/asian', 'GET: Asian Food!');
        $router->options('/asian', 'OPTIONS: Asian Food!');
        $router->post('/asian', 'POST: Asian Food!');

        $response = $router->dispatch(new ServerRequest('OPTIONS', '/asian'))->response();

        self::assertNotNull($response);
        self::assertEquals(
            'OPTIONS: Asian Food!',
            (string) $response->getBody(),
            'OPTIONS request should call the correct custom OPTIONS handler.'
        );
    }

    // =========================================================================
    // OldRouterTest unique methods
    // =========================================================================

    public function testNotRoutableController()
    {
        self::expectException('InvalidArgumentException');
        $this->router->instanceRoute('ANY', '/', new \stdClass);
        (string) $this->router->dispatch(new ServerRequest('get', '/'))->response()->getBody();
    }

    public function testNotRoutableControllerByName()
    {
        self::expectException('InvalidArgumentException');
        $this->router->classRoute('ANY', '/', '\\stdClass');
        (string) $this->router->dispatch(new ServerRequest('get', '/'))->response()->getBody();
    }

    #[DataProvider('providerForSingleRoutes')]
    public function testSingleRoutes($route, $path, $expectedParams)
    {
        $this->router->callbackRoute('get', $route, $this->callback);
        $r = $this->router->dispatch(new ServerRequest('get', $path));
        if ($r)
            $r->response();
        self::assertEquals($expectedParams, $this->result);
    }

    #[DataProvider('providerForLargeParams')]
    public function testLargeParams($route, $path, $expectedParams)
    {
        $this->router->callbackRoute('get', $route, $this->callback);
        $r = $this->router->dispatch(new ServerRequest('get', $path));
        if ($r)
            $r->response();
        self::assertEquals($expectedParams, $this->result);
    }

    #[DataProvider('providerForSpecialChars')]
    public function testSpecialChars($route, $path, $expectedParams)
    {
        $this->router->callbackRoute('get', $route, $this->callback);
        $r = $this->router->dispatch(new ServerRequest('get', $path));
        if ($r)
            $r->response();
        self::assertEquals($expectedParams, $this->result);
    }

    public static function providerForSingleRoutes()
    {
        return [
            [
                '/',
                '/',
                []
            ],
            [
                '/users',
                '/users',
                []
            ],
            [
                '/users/',
                '/users',
                []
            ],
            [
                '/users',
                '/users/',
                []
            ],
            [
                '/users/*',
                '/users/1',
                [1]
            ],
            [
                '/users/*/*',
                '/users/1/2',
                [1, 2]
            ],
            [
                '/users/*/lists',
                '/users/1/lists',
                [1]
            ],
            [
                '/users/*/lists/*',
                '/users/1/lists/2',
                [1, 2]
            ],
            [
                '/users/*/lists/*/*',
                '/users/1/lists/2/3',
                [1, 2, 3]
            ],
            [
                '/posts/*/*/*',
                '/posts/2010/10/10',
                [2010, 10, 10]
            ],
            [
                '/posts/*/*/*',
                '/posts/2010/10',
                [2010, 10]
            ],
            [
                '/posts/*/*/*',
                '/posts/2010',
                [2010]
            ],
            [
                '/posts/*/*/*',
                '/posts/2010/10///',
                [2010, 10]
            ],
            [
                '/posts/*/*/*',
                '/posts/2010/////',
                [2010]
            ],
            [
                '/posts/*/*/*',
                '/posts/2010/0/',
                [2010, 0]
            ],
            [
                '/users/*/*/lists/*/*',
                '/users/1/1B/lists/2/3',
                [1, '1B', 2, 3]
            ],
            [
                '/users/*/mounted-folder/**',
                '/users/alganet/mounted-folder/home/alganet/Projects/RespectRest/',
                ['alganet',['home', 'alganet', 'Projects', 'RespectRest']]
            ],
            [
                '/users/*/mounted-folder/*/**',
                '/users/alganet/mounted-folder/from-network/home/alganet/Projects/RespectRest/',
                ['alganet','from-network',['home', 'alganet', 'Projects', 'RespectRest']]
            ]
        ];
    }

    public static function providerForLargeParams()
    {
        return [
            [
                '/users/*/*/*/*/*/*/*',
                '/users/1',
                [1]
            ],
            [
                '/users/*/*/*/*/*/*/*',
                '/users/a/a/a/a/a/a/a',
                ['a', 'a', 'a', 'a', 'a', 'a', 'a']
            ],
            [
                '/users' . str_repeat('/*', 2500),
                '/users' . str_repeat('/xy', 2500),
                str_split(str_repeat('xy', 2500), 2)
            ],
            [
                '/users' . str_repeat('/*', 2500),
                '/users' . str_repeat('/abcdefghijklmnopqrstuvwxyz', 2500),
                str_split(str_repeat('abcdefghijklmnopqrstuvwxyz', 2500), 26)
            ],
            [
                '/users' . str_repeat('/*', 2500),
                '/users' . str_repeat('/abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz', 2500),
                str_split(str_repeat('abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz', 2500), 26 * 3)
            ],
        ];
    }

    public static function providerForSpecialChars()
    {
        return [
            [
                '/My Documents/*',
                '/My Documents/1',
                [1]
            ],
            [
                '/My Documents/*',
                '/My%20Documents/1',
                [1]
            ],
            [
                '/(.*)/*/[a-z]/*',
                '/(.*)/1/[a-z]/2',
                [1, 2]
            ],
            [
                '/shinny*/*',
                '/shinny*/2',
                [2]
            ],
        ];
    }

    public function testBindControllerNoParams()
    {
        $this->router->any('/users/*', new MyController);
        $result = (string) $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        self::assertEquals(json_encode(['alganet', 'get', []]), $result);
    }

    public function testBindControllerParams()
    {
        $this->router->any('/users/*', MyController::class, ['ok']);
        $result = (string) $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        self::assertEquals(json_encode(['alganet', 'get', ['ok']]), $result);
    }

    public function testBindControllerInstance()
    {
        $this->router->instanceRoute('ANY', '/users/*', new MyController('ok'));
        $result = (string) $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        self::assertEquals(json_encode(['alganet', 'get', ['ok']]), $result);
    }

    public function testBindControllerFactory()
    {
        $this->router->any('/users/*', MyController::class, function() {
            return new MyController('ok');
        });
        $result = (string) $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        self::assertEquals(json_encode(['alganet', 'get', ['ok']]), $result);
    }

    public function testBindControllerParams2()
    {
        $this->router->instanceRoute('ANY', '/users/*', new MyController('ok', 'foo', 'bar'));
        $result = (string) $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        self::assertEquals(json_encode(['alganet', 'get', ['ok', 'foo', 'bar']]), $result);
    }

    public function testBindControllerSpecial()
    {
        $this->router->instanceRoute('ANY', '/users/*', new MyController);
        $result = $this->router->dispatch(new ServerRequest('__construct', '/users/alganet'))->response();
        self::assertEquals(null, $result);
    }

    public function testBindControllerMultiMethods()
    {
        $this->router->instanceRoute('ANY', '/users/*', new MyController);
        $result = (string) $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        self::assertEquals(json_encode(['alganet', 'get', []]), $result);

        $result = (string) $this->router->dispatch(new ServerRequest('post', '/users/alganet'))->response()->getBody();
        self::assertEquals(json_encode(['alganet', 'post', []]), $result);
    }

    public function testProxyBy()
    {
        $result = null;
        $proxy = function() use (&$result) {
                $result = 'ok';
            };
        $this->router->get('/users/*', function() {

            })->by($proxy);
        $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response();
        self::assertEquals('ok', $result);
    }

    /**
     * @covers Respect\Rest\Router::always
     */
    public function testSimpleAlways()
    {
        $result = null;
        $proxy = function() use (&$result) {
                $result = 'ok';
            };
        $this->router->always('by', $proxy);
        $this->router->get('/users/*', function() {

            });
        $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response();
        self::assertEquals('ok', $result);
    }

    /**
     * @covers Respect\Rest\Router::always
     */
    public function testSimpleAlwaysAfter()
    {
        $result = null;
        $proxy = function() use (&$result) {
                $result = 'ok';
            };
        $this->router->get('/users/*', function() {

            });
        $this->router->always('by', $proxy);
        $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response();
        self::assertEquals('ok', $result);
    }

    public function testProxyThrough()
    {
        $result = null;
        $proxy = function() use (&$result) {
                $result = 'ok';
            };
        $this->router->get('/users/*', function() {

            })->through($proxy);
        $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response();
        self::assertEquals('ok', $result);
    }

    public function testProxyThroughOutput()
    {
        $proxy = function() {
                return function($output) {
                        return $output . 'ok';
                    };
            };
        $this->router->get('/users/*', function() {
                return 'ok';
            })->through($proxy);
        $result = (string) $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        self::assertEquals('okok', $result);
    }

    public function testMultipleProxies()
    {
        $result = [];
        $proxy1 = function($foo) use (&$result) {
                $result[] = $foo;
            };
        $proxy2 = function($bar) use (&$result) {
                $result[] = $bar;
            };
        $proxy3 = function($baz) use (&$result) {
                $result[] = $baz;
            };
        $this->router->get('/users/*/*/*', function($foo, $bar, $baz) use(&$result) {
                $result[] = 'main';
            })->by($proxy1)->through($proxy2)->through($proxy3);
        $this->router->dispatch(new ServerRequest('get', '/users/abc/def/ghi'))->response();
        self::assertSame(
            ['abc', 'main', 'def', 'ghi'], $result
        );
    }

    public function testProxyParamsByReference()
    {
        $resultProxy = null;
        $resultCallback = null;
        $proxy1 = function($foo=null, $abc=null) use (&$resultProxy) {
                $resultProxy = func_get_args();
            };
        $callback = function($bar, $foo=null) use(&$resultCallback) {
                $resultCallback = func_get_args();
            };
        $this->router->get('/users/*/*', $callback)->by($proxy1);
        $this->router->dispatch(new ServerRequest('get', '/users/abc/def'))->response();
        self::assertEquals(['def', null], $resultProxy);
        self::assertEquals(['abc', 'def'], $resultCallback);
    }

    public function testProxyReturnFalse()
    {
        $result = [];
        $proxy1 = function($foo) use (&$result) {
                $result[] = $foo;
                return false;
            };
        $proxy2 = function($bar) use (&$result) {
                $result[] = $bar;
            };
        $proxy3 = function($baz) use (&$result) {
                $result[] = $baz;
            };
        $this->router->get('/users/*/*/*', function($foo, $bar, $baz) use(&$result) {
                $result[] = 'main';
            })->by($proxy1)->through($proxy2)->through($proxy3);
        $this->router->dispatch(new ServerRequest('get', '/users/abc/def/ghi'))->response();
        self::assertSame(
            ['abc'], $result
        );
    }

    public function testWildcardOrdering()
    {
        $this->router->any('/posts/*/*', function($year, $month) {
                return 10;
            }
        );
        $this->router->any('/**', function($userName) {
                return 5;
            }
        );
        self::assertEquals(
            '10', (string) $this->router->dispatch(new ServerRequest('get', '/posts/2010/20'))->response()->getBody()
        );
        self::assertEquals(
            '5', (string) $this->router->dispatch(new ServerRequest('get', '/anything'))->response()->getBody()
        );
    }

    public function testOrdering()
    {
        $this->router->any('/users/*', function($userName) {
                return 5;
            }
        );
        $this->router->any('/users/*/*', function($year, $month) {
                return 10;
            }
        );
        self::assertEquals(
            '5', (string) $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody()
        );
        self::assertEquals(
            '10', (string) $this->router->dispatch(new ServerRequest('get', '/users/2010/20'))->response()->getBody()
        );
    }

    public function testOrderingSpecific()
    {
        $this->router->any('/users/*/*', function($year, $month) {
                return 10;
            }
        );
        $this->router->any('/users/lists/*', function($userName) {
                return 5;
            }
        );
        self::assertEquals(
            '5', (string) $this->router->dispatch(new ServerRequest('get', '/users/lists/alganet'))->response()->getBody()
        );
        self::assertEquals(
            '10', (string) $this->router->dispatch(new ServerRequest('get', '/users/foobar/alganet'))->response()->getBody()
        );
    }

    public function testOrderingSpecific2()
    {
        $this->router->any('/', function() {
                return 2;
            }
        );
        $this->router->any('/*', function() {
                return 3;
            }
        );
        $this->router->any('/*/versions', function() {
                return 4;
            }
        );
        $this->router->any('/*/versions/*', function() {
                return 5;
            }
        );
        $this->router->any('/*/*', function() {
                return 6;
            }
        );
        self::assertEquals(
            '2', (string) $this->router->dispatch(new ServerRequest('get', '/'))->response()->getBody()
        );
        self::assertEquals(
            '3', (string) $this->router->dispatch(new ServerRequest('get', '/foo'))->response()->getBody()
        );
        self::assertEquals(
            '4', (string) $this->router->dispatch(new ServerRequest('get', '/foo/versions'))->response()->getBody()
        );
        self::assertEquals(
            '5', (string) $this->router->dispatch(new ServerRequest('get', '/foo/versions/1.0'))->response()->getBody()
        );
        self::assertEquals(
            '6', (string) $this->router->dispatch(new ServerRequest('get', '/foo/bar'))->response()->getBody()
        );
    }

    public function testExperimentalShell()
    {
        $router = new Router(new Psr17Factory());
        $router->install('/**', function() {
                return 'Installed ' . implode(', ', func_get_arg(0));
            }
        );
        $commandLine = 'install apache php mysql';
        $commandArgs = explode(' ', $commandLine);
        $output = (string) $router->dispatch(
                new ServerRequest(array_shift($commandArgs), '/' . implode('/', $commandArgs))
            )->response()->getBody();
        self::assertEquals('Installed apache, php, mysql', $output);
    }

    public function testAccept()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept', 'application/json');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', function() {
                return range(0, 10);
            })->accept(['application/json' => 'json_encode']);
        $r = (string) $this->router->dispatchRequest($request)->response()->getBody();
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testAcceptCharset()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Charset', 'utf-8');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', function() {
                return 'açaí';
            })->acceptCharset(['utf-8' => fn ($data) => mb_convert_encoding($data, 'ISO-8859-1', 'UTF-8')]);
        $r = (string) $this->router->dispatchRequest($request)->response()->getBody();
        self::assertEquals(mb_convert_encoding('açaí', 'ISO-8859-1', 'UTF-8'), $r);
    }

    public function testAcceptEncoding()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Encoding', 'myenc');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', function() {
                return 'foobar';
            })->acceptEncoding(['myenc' => 'strrev']);
        $r = (string) $this->router->dispatchRequest($request)->response()->getBody();
        self::assertEquals(strrev('foobar'), $r);
    }

    public function testAcceptUrl()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet.json'))
            ->withHeader('Accept', '*/*');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', function($screenName) {
                return range(0, 10);
            })->accept(['.json' => 'json_encode']);
        $r = (string) $this->router->dispatchRequest($request)->response()->getBody();
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testAcceptUrlNoParameters()
    {
        $serverRequest = (new ServerRequest('get', '/users.json'))
            ->withHeader('Accept', '*/*');
        $request = new Request($serverRequest);
        $this->router->get('/users', function() {
                return range(0, 10);
            })->accept(['.json' => 'json_encode']);
        $r = (string) $this->router->dispatchRequest($request)->response()->getBody();
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testFileExtension()
    {
        $request = new Request(new ServerRequest('get', '/users.json/10.20'));
        $this->router->get('/users.json/*', function($param) {
                [$min, $max] = explode('.', $param);
                return range($min, $max);
            });
        $r = (string) $this->router->dispatchRequest($request)->response()->getBody();
        self::assertEquals(json_encode(range(10, 20)), $r);
    }

    public function testAcceptGeneric2()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept', '*/*');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', function() {
                return range(0, 10);
            })->accept(['application/json' => 'json_encode']);
        $r = (string) $this->router->dispatchRequest($request)->response()->getBody();
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testAcceptLanguage()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'en');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', function() {

            })->acceptLanguage([
            'en-US' => function() {
                return 'Hi there';
            },
            'pt-BR' => function() {
                return 'Olá!';
            }]);
        $r = (string) $this->router->dispatchRequest($request)->response()->getBody();
        self::assertEquals('Hi there', $r);
    }

    public function testAcceptLanguage2()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', function() {

            })->acceptLanguage([
            'en-US' => function() {
                return 'Hi there';
            },
            'pt-BR' => function() {
                return 'Olá!';
            }]);
        $r = (string) $this->router->dispatchRequest($request)->response()->getBody();
        self::assertEquals('Olá!', $r);
    }

    public function testAcceptOrder()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt,en');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', function() {

            })->acceptLanguage([
            'en' => function() {
                return 'Hi there';
            },
            'pt' => function() {
                return 'Olá!';
            }]);
        $r = (string) $this->router->dispatchRequest($requestBoth)->response()->getBody();
        self::assertEquals('Olá!', $r);
    }

    public function testUniqueRoutine()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt,en');
        $requestBoth = new Request($serverRequest);
        $neverRun = false;
        $this->router->get('/users/*', function() {

            })->acceptLanguage([
            'en' => function() use (&$neverRun){
                $neverRun = true;
            },
            'pt' => function() use (&$neverRun){
                $neverRun = true;
            }])->acceptLanguage([
            'en' => function() {
                return 'dsfdfsdfsdf';
            },
            'pt' => function() {
                return 'sdfsdfsdfdf!';
            }]);
        $r = $this->router->dispatchRequest($requestBoth)->response();
        self::assertFalse($neverRun);
    }

    public function testAcceptMulti()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt,en')
            ->withHeader('Accept', 'application/json');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', function($data) {
                return '034930984';
            })->acceptLanguage([
            'en' => function() {
                return 'Hi there';
            },
            'pt' => function() {
                return 'Olá!';
            }])->accept([
            'application/json' => 'json_encode'
        ]);
        $r = (string) $this->router->dispatchRequest($requestBoth)->response()->getBody();
        self::assertEquals('"Ol\u00e1!"', $r);
    }

    public function testAcceptOrderX()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'x-klingon,en');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', function() {

            })->acceptLanguage([
            'en' => function() {
                return 'Hi there';
            },
            'klingon-tr' => function() {
                return 'nuqneH';
            }]);
        $r = (string) $this->router->dispatchRequest($requestBoth)->response()->getBody();
        self::assertEquals('nuqneH', $r);
    }

    public function testAcceptOrderQuality()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt;q=0.7,en');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', function() {

            })->acceptLanguage([
            'en-US' => function() {
                return 'Hi there';
            },
            'pt-BR' => function() {
                return 'Olá!';
            }]);
        $r = (string) $this->router->dispatchRequest($requestBoth)->response()->getBody();
        self::assertEquals('Hi there', $r);
    }

    public function testLastModifiedSince()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', function() {
                return 'hi!';
            })->lastModified(
            function() {
                return new \DateTime('2011-11-11 11:11:12');
            });
        $r = (string) $this->router->dispatchRequest($requestBoth)->response()->getBody();
        self::assertEquals('hi!', $r);
    }

    public function testLastModifiedSince2()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', function() {
                return 'hi!';
            })->lastModified(
            function() {
                return new \DateTime('2011-11-11 11:11:10');
            });
        $response = $this->router->dispatchRequest($requestBoth)->response();
        self::assertEquals(304, $response->getStatusCode());
        self::assertEquals('Fri, 11 Nov 2011 11:11:10 +0000', $response->getHeaderLine('Last-Modified'));
    }

    public function testLastModifiedSince3()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', function() {
                return 'hi!';
            })->lastModified(
            function() {
                return new \DateTime('2011-11-11 11:11:11');
            });
        $response = $this->router->dispatchRequest($requestBoth)->response();
        self::assertEquals(304, $response->getStatusCode());
        self::assertEquals('Fri, 11 Nov 2011 11:11:11 +0000', $response->getHeaderLine('Last-Modified'));
    }

    public function testContenType()
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Content-Type', 'text/xml');
        $requestBoth = new Request($serverRequest);
        $result = null;
        $this->router->get('/users/*', function() {

            })->contentType([
            'text/json' => function() {

            },
            'text/xml' => function() use (&$result) {
                $result = 'ok';
            }]);
        $r = $this->router->dispatchRequest($requestBoth)->response();
        self::assertEquals('ok', $result);
    }

    public function testVirtualHost()
    {
        $router = new Router(new Psr17Factory(), '/myvh');
        $ok = false;
        $router->get('/alganet', function() use (&$ok) {
                $ok = true;
            }
        );
        $router->dispatch(new ServerRequest('get', '/myvh/alganet'))->response();
        self::assertTrue($ok);
    }

    public function testVirtualHostEmpty()
    {
        $router = new Router(new Psr17Factory(), '/myvh');
        $ok = false;
        $router->get('/', function() use (&$ok) {
                $ok = true;
            }
        );
        $router->dispatch(new ServerRequest('get', '/myvh'))->response();
        self::assertTrue($ok);
    }

    public function testVirtualHostIndex()
    {
        $router = new Router(new Psr17Factory(), '/myvh/index.php');
        $ok = false;
        $router->get('/', function() use (&$ok) {
                $ok = true;
            }
        );
        $router->dispatch(new ServerRequest('get', '/myvh/index.php'))->response();
        self::assertTrue($ok);
    }

    public function testCreateUri()
    {
        $r = new Router(new Psr17Factory());
        $ro = $r->any('/users/*/test/*', function() {

            });
        self::assertEquals(
            '/users/alganet/test/php', $ro->createUri("alganet", "php")
        );
        $r->isAutoDispatched = false;
    }

    public function testForward()
    {
        $r = new Router(new Psr17Factory());
        $ro1 = $r->any('/users/*', function($user) {
            return $user;
        });
        $ro2 = $r->any('/*', function($user) use ($ro1) {
            return $ro1;
        });
        $response = (string) $r->dispatch(new ServerRequest('get', '/alganet'))->response()->getBody();
        self::assertEquals('alganet', $response);
    }

    /**
     * @group issues
     * @ticket 37
     **/
    public function test_optional_parameter_in_class_routes()
    {
        $r = new Router(new Psr17Factory());
        $r->any('/optional/*', MyOptionalParamRoute::class);
        $response = (string) $r->dispatch(new ServerRequest('get', '/optional'))->response()->getBody();
        self::assertEquals('John Doe', $response);
    }

    // =========================================================================
    // NewRouterTest unique methods
    // =========================================================================

    public function test_bad_request_header()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function() { return 'ok'; })->when(function(){return false;});
        $response = $router->dispatch(new ServerRequest('get', '/'))->response();
        self::assertNull($response);
    }

    public function test_method_not_allowed_header()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function() { return 'ok'; });
        $router->put('/', function() { return 'ok'; });
        $response = $router->dispatch(new ServerRequest('delete', '/'))->response();
        self::assertNull($response, 'Method not allowed — route should be null');
    }

    public function test_method_not_allowed_header_with_conneg()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function() { return 'ok'; })
                     ->accept(['text/html' => function($d) {return $d;}]);
        $response = $router->dispatch(new ServerRequest('delete', '/'))->response();
        self::assertNull($response, 'Method not allowed — route should be null');
    }

    public function test_transparent_options_allow_methods()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function() { return 'ok'; });
        $router->post('/', function() { return 'ok'; });
        $response = $router->dispatch(new ServerRequest('options', '/'))->response();
        self::assertNull($response);
    }

    public function test_transparent_global_options_allow_methods()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function() { return 'ok'; });
        $router->post('/', function() { return 'ok'; });
        $response = $router->dispatch(new ServerRequest('options', '*'))->response();
        self::assertNull($response);
    }

    public function test_method_not_acceptable()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function() { return 'ok'; })
                     ->accept(['foo/bar' => function($d) {return $d;}]);
        $response = $router->dispatch(new ServerRequest('get', '/'))->response();
        self::assertNotNull($response);
        self::assertEquals(406, $response->getStatusCode());
    }

    public function test_append_routine_honours_routine_chaining()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/one-time', function() { return "one-time"; })
            ->appendRoutine(new Routines\Through(function ($data) {return function ($data) { return "$data-through1";};}))
            ->through(function ($data) {return function ($data) {return "$data-through2";};});
        $response = $router->dispatch(new ServerRequest('GET', '/one-time'));
        self::assertEquals('one-time-through1-through2', $response);
    }

    public function test_callback_gets_param_array()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/one-time/*', function($frag, $param1, $param2) {
            return "one-time-$frag-$param1-$param2";
        }, ['addl','add2']);
        $response = $router->dispatch(new ServerRequest('GET', '/one-time/1'));
        self::assertEquals('one-time-1-addl-add2', $response);
    }

    public function test_http_method_head()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function() {
            return 'ok';
        });
        $getResponse  = $router->dispatch(new ServerRequest('GET', '/'));
        self::assertEquals('ok', (string) $getResponse);
        // HEAD should also match GET route
        $headResponse = $router->dispatch(new ServerRequest('HEAD', '/'));
        // HEAD dispatches to GET handler; verify it does not error
        self::assertNotNull($headResponse);
    }

    public function test_http_method_head_with_classes_and_routines()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', HeadTestStub::class, ['X-Burger: With Cheese!'])
                     ->when(function(){return true;});
        $getResponse  = $router->dispatch(new ServerRequest('GET', '/'));
        self::assertEquals('ok', (string) $getResponse->response()->getBody());
        // HEAD should match GET route
        $headResponse = $router->dispatch(new ServerRequest('HEAD', '/'));
        self::assertNotNull($headResponse);
    }

    public function test_user_agent_class()
    {
        $u = new KnowsUserAgent(['*' => function () {}]);

        self::assertFalse($u->knowsCompareItems('a','b'));
        self::assertFalse($u->knowsCompareItems('c','b'));
        self::assertTrue($u->knowsCompareItems(1,'1'));
        self::assertTrue($u->knowsCompareItems('0',''));
        self::assertTrue($u->knowsCompareItems('a','*'));
    }

    public function test_user_agent_content_negotiation()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function () {
            return 'unknown';
        })->userAgent([
            'FIREFOX' => function() { return 'FIREFOX'; },
            'IE' => function() { return 'IE'; },
        ]);
        $serverRequest = (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX');
        $response = $router->dispatch($serverRequest);
        self::assertEquals('FIREFOX', $response);
    }

    public function test_user_agent_content_negotiation_fallback()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function () {
            return 'unknown';
        })->userAgent([
            '*' => function() { return 'IE'; },
        ]);
        $serverRequest = (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX');
        $response = $router->dispatch($serverRequest);
        self::assertEquals('IE', $response);
    }

    public function test_stream_routine()
    {
        $done                            = false;
        $self                            = $this;
        $serverRequest                   = (new ServerRequest('GET', '/input'))
                                            ->withHeader('Accept-Encoding', 'deflate');
        $request                         = new Request($serverRequest);
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/input', function() { return fopen('php://input', 'r+'); })
                     ->acceptEncoding([
                        'deflate' => function($stream) use ($self, &$done) {
                            $done = true;
                            $self->assertIsResource($stream);
                            stream_filter_append($stream, 'zlib.deflate', STREAM_FILTER_READ);
                            return $stream;
                        }
                     ]);

        $response = $router->run($request);
        self::assertTrue($done);
        self::assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
    }

    public function test_optional_parameter_in_function_routes()
    {
        $r = new Router(new Psr17Factory());
        $r->any('/optional/*', function($user=null){
            return $user ?: 'John Doe';
        });
        $response = $r->dispatch(new ServerRequest('get', '/optional'))->response();
        self::assertEquals('John Doe', (string) $response->getBody());
    }

    public function test_optional_parameter_in_function_routes_multiple()
    {
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

    public function test_two_optional_parameters_in_function_routes()
    {
        $r = new Router(new Psr17Factory());
        $r->any('/optional/*/*', function($user=null, $list=null){
            return $user . $list;
        });
        $response = $r->dispatch(new ServerRequest('get', '/optional/Foo/Bar'))->response();
        self::assertEquals('FooBar', (string) $response->getBody());
    }

    public function test_two_optional_parameters_one_passed_in_function_routes()
    {
        $r = new Router(new Psr17Factory());
        $r->any('/optional/*/*', function($user=null, $list=null){
            return $user . $list;
        });
        $response = $r->dispatch(new ServerRequest('get', '/optional/Foo'))->response();
        self::assertEquals('Foo', (string) $response->getBody());
    }

    public function test_single_last_param()
    {
        $r = new Router(new Psr17Factory());
        $args = [];
        $r->any('/documents/*', function($documentId) use (&$args) {
            $args = func_get_args();
        });
        $r->dispatch(new ServerRequest('get', '/documents/1234'))->response();
        self::assertEquals(['1234'], $args);
    }

    public function test_single_last_param2()
    {
        $r = new Router(new Psr17Factory());
        $args = [];
        $r->any('/documents/**', function($documentsPath) use (&$args) {
            $args = func_get_args();
        });
        $r->dispatch(new ServerRequest('get', '/documents/foo/bar'))->response();
        self::assertEquals([['foo', 'bar']], $args);
    }

    public function test_catchall_on_root_call_should_get_callback_parameter()
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

    public static function provider_content_type()
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
    public function test_automatic_content_type_header($ctype)
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
    public function test_wildcard_automatic_content_type_header($ctype)
    {
        $r = new Router(new Psr17Factory());
        $r->get('/auto', '')->accept([$ctype=>'json_encode']);
        $serverRequest = (new ServerRequest('get', '/auto'))->withHeader('Accept', '*/*');
        $response = $r->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertEquals($ctype, $response->getHeaderLine('Content-Type'));
    }

    public function test_request_forward()
    {
        $r = new Router(new Psr17Factory());
        $r1 = $r->get('/route1', 'route1');
        $response = (string) $r->dispatch(new ServerRequest('get', '/route1'))->response()->getBody();
        self::assertEquals('route1', $response);
        $r2 = $r->get('/route2', 'route2');
        $response = (string) $r->dispatch(new ServerRequest('get', '/route2'))->response()->getBody();
        self::assertEquals('route2', $response);
        $r2->by(function() use ($r1) { return $r1;});
        $response = (string) $r->dispatch(new ServerRequest('get', '/route2'))->response()->getBody();
        self::assertEquals('route1', $response);
    }

    public function test_negotiate_acceptable_complete_headers()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/accept', function() { return 'ok'; })
                     ->accept(['foo/bar' => function($d) {return $d;}])
                     ->acceptLanguage(['13375p34|<' => function($d) {return $d;}]);
        $serverRequest = (new ServerRequest('get', '/accept'))
            ->withHeader('Accept', 'foo/bar')
            ->withHeader('Accept-Language', '13375p34|<');
        $response = $router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertEquals('ok', (string) $response->getBody());
        self::assertEquals('foo/bar', $response->getHeaderLine('Content-Type'));
        self::assertStringContainsString('accept-language', $response->getHeaderLine('Vary'));
        self::assertEquals('/accept', $response->getHeaderLine('Content-Location'));
        self::assertNotEmpty($response->getHeaderLine('Expires'));
        self::assertNotEmpty($response->getHeaderLine('Cache-Control'));
    }

    public function test_accept_content_type_header()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function() { return 'ok'; })
                     ->accept(['foo/bar' => function($d) {return $d;}]);
        $serverRequest = (new ServerRequest('get', '/'))->withHeader('Accept', 'foo/bar');
        $response = $router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertEquals('foo/bar', $response->getHeaderLine('Content-Type'));
    }

    public function test_accept_content_language_header()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', function() { return 'ok'; })
                     ->acceptLanguage(['13375p34|<' => function($d) {return $d;}]);
        $serverRequest = (new ServerRequest('get', '/'))->withHeader('Accept-Language', '13375p34|<');
        $response = $router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertStringContainsString('accept-language', $response->getHeaderLine('Vary'));
    }

    public static function provider_content_type_extension()
    {
        return [
            ['text/html', '.html'],
            ['application/json', '.json'],
            ['text/xml', '.xml']
        ];
    }

    #[DataProvider('provider_content_type_extension')]
    public function test_do_not_set_automatic_content_type_header_for_extensions($ctype, $ext)
    {
        $r = new Router(new Psr17Factory());
        $r->get('/auto', '')->accept([$ext => 'json_encode']);

        $r = $r->dispatch(new ServerRequest('get', '/auto'.$ext))->response();
        // Extension-based accept should not set Content-Type header
        self::assertNotNull($r);
    }

    /**
     * @covers \Respect\Rest\Routes\AbstractRoute
     */
    public function test_optional_parameters_should_be_allowed_only_at_the_end_of_the_path()
    {
        $r = new Router(new Psr17Factory());
        $r->get('/users/*/photos/*', function($username, $photoId=null) {
            return 'match';
        });
        $psrResponse = $r->dispatch(new ServerRequest('get', '/users/photos'))->response();
        $response = $psrResponse !== null ? (string) $psrResponse->getBody() : '';
        self::assertNotEquals('match', $response);
    }

    public function test_route_ordering_with_when()
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

    public function test_when_should_be_called_only_on_existent_methods()
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;

        $r1 = $router->any('/meow/*', RouteKnowsGet::class);
        $r1->accept(['application/json' => 'json_encode']);

        $router->any('/moo/*', RouteKnowsNothing::class);

        $serverRequest = (new ServerRequest('get', '/meow/blub'))->withHeader('Accept', 'application/json');
        $out = (string) $router->run(new Request($serverRequest))->getBody();

        self::assertEquals('"ok: blub"', $out);
    }

    public function test_request_should_be_available_from_router_after_dispatching()
    {
        $request = new Request(new ServerRequest('get', '/foo'));
        $router = new Router(new Psr17Factory());
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
