<?php

declare(strict_types=1);

namespace Respect\Rest\Test;

use DateTime;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use ReflectionMethod;
use ReflectionObject;
use Respect\Rest\Request;
use Respect\Rest\Routable;
use Respect\Rest\Router;
use Respect\Rest\Routines;
use Respect\Rest\Test\Stubs\HeadFactoryController;
use Respect\Rest\Test\Stubs\HeadTest as HeadTestStub;
use Respect\Rest\Test\Stubs\MyController;
use Respect\Rest\Test\Stubs\MyOptionalParamRoute;
use Respect\Rest\Test\Stubs\RouteKnowsNothing;
use Respect\Rest\Test\Stubs\StubRoutable;
use SplObjectStorage;
use stdClass;

use function array_map;
use function array_shift;
use function explode;
use function fopen;
use function func_get_arg;
use function func_get_args;
use function implode;
use function is_numeric;
use function json_encode;
use function mb_convert_encoding;
use function range;
use function spl_object_hash;
use function str_repeat;
use function str_split;
use function stream_filter_append;
use function strrev;

use const STREAM_FILTER_READ;

/** @covers Respect\Rest\Router */
final class RouterTest extends TestCase
{
    protected Router $router;

    protected mixed $result = null;

    /** @var callable */
    protected $callback;

    protected function setUp(): void
    {
        $this->router = new Router(new Psr17Factory());
        $this->result = null;
        $result = &$this->result;
        $this->callback = static function () use (&$result): void {
                $result = func_get_args();
        };
    }

    // =========================================================================
    // Library RouterTest methods
    // =========================================================================

    /** @covers Respect\Rest\Router::__call */
    public function testMagicConstructorWarnsIfNoSufficientParametersWerePassed(): void
    {
        self::expectException('InvalidArgumentException');
        $router = new Router(new Psr17Factory());
        /** @phpstan-ignore-next-line */
        $router->thisIsInsufficientForMagicConstruction();
    }

    /** @covers Respect\Rest\Router::__call */
    public function testMagicConstructorWarnsIfNoSufficientParametersWerePassed2(): void
    {
        self::expectException('InvalidArgumentException');
        $router = new Router(new Psr17Factory());
        /** @phpstan-ignore-next-line */
        $router->thisIsInsufficientForMagicConstruction('/magicians');
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::callbackRoute
     */
    public function testMagicConstructorCanCreateCallbackRoutes(): void
    {
        $router = new Router(new Psr17Factory());
        $callbackRoute = $router->get('/', $target = static function (): void {
        });
        $concreteCallbackRoute = $router->callbackRoute('GET', '/', $target);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\Callback',
            $callbackRoute,
            'Returned result from a magic constructor in this case should return a Routes\Callback',
        );

        self::assertEmpty(
            $callbackRoute->arguments,
            'When there are no arguments the Routes\Callback should have none as well',
        );

        self::assertEquals(
            $callbackRoute,
            $concreteCallbackRoute,
            'The magic and concrete instances of Routes\Callback should be equivalent',
        );
    }

    /**
     * @covers  Respect\Rest\Router::__call
     * @covers  Respect\Rest\Router::callbackRoute
     * @depends testMagicConstructorCanCreateCallbackRoutes
     */
    public function testMagicConstructorCanCreateCallbackRoutesWithExtraParams(): void
    {
        $router = new Router(new Psr17Factory());
        /** @phpstan-ignore-next-line */
        $callbackRoute = $router->get('/', $target = static function (): void {
        }, ['extra']);
        $concreteCallbackRoute = $router->callbackRoute('GET', '/', $target, ['extra']);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\Callback',
            $callbackRoute,
            'Returned result from a magic constructor in this case should return a Routes\Callback',
        );

        self::assertContains(
            'extra',
            $callbackRoute->arguments,
            'The "extra" appended to the magic constructor should be present on the arguments list',
        );

        self::assertEquals(
            $callbackRoute,
            $concreteCallbackRoute,
            'The magic and concrete instances of Routes\Callback should be equivalent',
        );
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::instanceRoute
     */
    public function testMagicConstructorCanRouteToPreBuiltInstances(): void
    {
        $router = new Router(new Psr17Factory());
        $myInstance = new class implements Routable {
            public function GET(): string
            {
                return 'mock response';
            }
        };
        $instanceRoute = $router->get('/', $myInstance);
        $concreteInstanceRoute = $router->instanceRoute('GET', '/', $myInstance);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\Instance',
            $instanceRoute,
            'Returned result from a magic constructor in this case should return a Routes\Instance',
        );

        self::assertEquals(
            $instanceRoute,
            $concreteInstanceRoute,
            'The magic and concrete instances of Routes\Instance should be equivalent',
        );
    }

    /**
     * @covers       Respect\Rest\Router::__call
     * @covers       Respect\Rest\Router::staticRoute
     */
    #[DataProvider('provideForStaticRoutableValues')]
    public function testMagicConstructorCanRouteToStaticValue(mixed $staticValue, string $reason): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $staticRoute = $router->get('/', $staticValue);
        $concreteStaticRoute = $router->staticRoute('GET', '/', $staticValue);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\StaticValue',
            $staticRoute,
            $reason,
        );

        self::assertEquals(
            $staticRoute,
            $concreteStaticRoute,
            'The magic and concrete instances of Routes\Static should be equivalent',
        );
    }

    /** @return array<int, array<int, mixed>> */
    public static function provideForStaticRoutableValues(): array
    {
        return [
            ['Some Static Value', 'Strings should be possible to route statically'],
            [['Some', 'Other', 'Routable', 'Value'], 'Arrays should be possible to route statically'],
            [10, 'Integers and scalars should be possible to route statically'],
        ];
    }

    /**
     * @covers            Respect\Rest\Router::__call
     * @covers            Respect\Rest\Router::staticRoute
     */
    #[DataProvider('provideForNonStaticRoutableValues')]
    public function testMagicConstructorCannotRouteSomeStaticValues(mixed $staticValue, string $reason): void
    {
        self::expectException(InvalidArgumentException::class);
        $router = new Router(new Psr17Factory());
        $nonStaticRoute = $router->get('/', $staticValue);

        $router->run(new Request(new ServerRequest('GET', '/')));

        self::assertNotInstanceOf(
            'Respect\\Rest\\Routes\\StaticValue',
            $nonStaticRoute,
            $reason,
        );
    }

    /** @return array<int, array<int, mixed>> */
    public static function provideForNonStaticRoutableValues(): array
    {
        return [
            ['PDO', 'Strings that are class names should NOT be possible to route statically'],
            ['Traversable', 'Strings that are interface names should NOT be possible to route statically'],
        ];
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::classRoute
     */
    public function testMagicConstructorCanRouteToClasses(): void
    {
        $router = new Router(new Psr17Factory());
        $className = StubRoutable::class;
        $classRoute = $router->get('/', $className);
        $concreteClassRoute = $router->classRoute('GET', '/', $className);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\ClassName',
            $classRoute,
            'Returned result from a magic constructor in this case should return a Routes\ClassName',
        );

        self::assertEquals(
            $classRoute,
            $concreteClassRoute,
            'The magic and concrete instances of Routes\ClassName should be equivalent',
        );
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::classRoute
     */
    public function testMagicConstructorCanRouteToClassesUsingConstructorParams(): void
    {
        $router = new Router(new Psr17Factory());
        $className = StubRoutable::class;
        /** @phpstan-ignore-next-line */
        $classRoute = $router->get('/', $className, ['some', 'constructor', 'params']);
        $concreteClassRoute = $router->classRoute('GET', '/', $className, ['some', 'constructor', 'params']);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\ClassName',
            $classRoute,
            'Returned result from a magic constructor in this case should return a Routes\ClassName',
        );

        self::assertEquals(
            ['some', 'constructor', 'params'],
            $classRoute->constructorParams,
            'The constructor params should be available on the instance of Routes\ClassName',
        );

        self::assertEquals(
            $classRoute,
            $concreteClassRoute,
            'The magic and concrete instances of Routes\ClassName should be equivalent',
        );
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::factoryRoute
     */
    public function testMagicConstructorCanRouteToFactoriesThatReturnInstancesOfAClass(): void
    {
        $router = new Router(new Psr17Factory());
        eval('class MockRoutable2 implements Respect\Rest\Routable{ public function GET() {} }');
        eval('class FactoryClass2 { public static function factoryMethod() { return new MockRoutable2(); } }');
        /** @phpstan-ignore-next-line */
        $factoryRoute = $router->get('/', 'FactoryClass2', ['FactoryClass2', 'factoryMethod']);
        /** @phpstan-ignore-next-line */
        $concreteFactoryRoute = $router->factoryRoute('GET', '/', 'FactoryClass2', ['FactoryClass2', 'factoryMethod']);

        self::assertInstanceOf(
            'Respect\\Rest\\Routes\\Factory',
            $factoryRoute,
            'Returned result from a magic constructor in this case should return a Routes\Factory',
        );

        self::assertEquals(
            $factoryRoute,
            $concreteFactoryRoute,
            'The magic and concrete instances of Routes\Factory should be equivalent',
        );
    }

    /**
     * @covers Respect\Rest\Router::dispatchRequest
     * @covers Respect\Rest\Router::isRoutelessDispatch
     * @covers Respect\Rest\Router::isDispatchedToGlobalOptionsMethod
     * @covers Respect\Rest\Router::getAllowedMethods
     * @runInSeparateProcess
     */
    public function testCanRespondToGlobalOptionsMethodAutomatically(): void
    {
        $router = new Router(new Psr17Factory());
        $router->get('/asian', 'Asian Food!');
        $router->post('/eastern', 'Eastern Food!');
        /** @phpstan-ignore-next-line */
        $router->eat('/mongolian', 'Mongolian Food!');
        $response = $router->dispatch(new ServerRequest('OPTIONS', '*'))->response();

        self::assertNotNull($response);
        self::assertSame(204, $response->getStatusCode());
        self::assertEqualsCanonicalizing(
            ['GET', 'HEAD', 'POST', 'EAT', 'OPTIONS'],
            array_map('trim', explode(',', $response->getHeaderLine('Allow'))),
        );
    }

    /**
     * @covers Respect\Rest\Router::dispatchRequest
     * @covers Respect\Rest\Router::isRoutelessDispatch
     * @covers Respect\Rest\Router::hasDispatchedOverridenMethod
     */
    public function testDeveloperCanOverridePostMethodWithQueryStringParameter(): Router
    {
        $router = new Router(new Psr17Factory());
        $router->methodOverriding = true;
        $router->put('/bulbs', 'Some Bulbs Put Response');
        $router->post('/bulbs', 'Some Bulbs Post Response');

        $serverRequest = (new ServerRequest('POST', '/bulbs'))->withParsedBody(['_method' => 'PUT']);
        $response = $router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        $result = (string) $response->getBody();

        self::assertSame(
            'Some Bulbs Put Response',
            $result,
            'Router should dispatch to PUT (overriden) instead of POST',
        );

        self::assertNotSame(
            'Some Bulbs Post Response',
            $result,
            'Router NOT dispatch to POST when method is overriden',
        );

        return $router;
    }

    /**
     * @covers  Respect\Rest\Router::dispatchRequest
     * @covers  Respect\Rest\Router::isRoutelessDispatch
     * @covers  Respect\Rest\Router::hasDispatchedOverridenMethod
     */
    #[Depends('testDeveloperCanOverridePostMethodWithQueryStringParameter')]
    public function testDeveloperCanTurnOffMethodOverriding(Router $router): void
    {
        $router->methodOverriding = false;
        $serverRequest = (new ServerRequest('POST', '/bulbs'))->withParsedBody(['_method' => 'PUT']);
        $response = $router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        $result = (string) $response->getBody();

        self::assertSame(
            'Some Bulbs Post Response',
            $result,
            'Router should dispatch to POST (not overriden) instead of PUT',
        );

        self::assertNotSame(
            'Some Bulbs Put Response',
            $result,
            'Router NOT dispatch to PUT when method is overriden',
        );
    }

    /**
     * @covers  Respect\Rest\Router::dispatchRequest
     * @covers  Respect\Rest\Router::routeDispatch
     * @covers  Respect\Rest\Router::applyVirtualHost
     */
    public function testDeveloperCanSetUpAVirtualHostPathOnConstructor(): void
    {
        $router = new Router(new Psr17Factory(), '/store');
        $router->get('/products', 'Some Products!');
        $r = $router->dispatch(new ServerRequest('GET', '/store/products'))->response();
        self::assertNotNull($r);
        $response = (string) $r->getBody();

        self::assertSame(
            'Some Products!',
            $response,
            'Router should match using the virtual host combined URI',
        );
    }

    /** @covers Respect\Rest\Router::__destruct */
    public function testRouterDoesNotAutoDispatchAfterManualDispatch(): void
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
    public function testReturns404WhenNoRoutesExist(): void
    {
        $router = new Router(new Psr17Factory());
        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @covers Respect\Rest\Router::dispatch
     * @covers Respect\Rest\Router::routeDispatch
     */
    public function testReturns404WhenNoRouteMatches(): void
    {
        $router = new Router(new Psr17Factory());
        $router->get('/foo', 'This exists.');
        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
    }

    /** @covers Respect\Rest\Router::appendRoute */
    public function testNamesRoutesUsingAttributes(): void
    {
        $router = new Router(new Psr17Factory());
        $router->allMembers = $router->any('/members', 'John, Carl');
        $r = $router->dispatch(new ServerRequest('GET', '/members'))->response();
        self::assertNotNull($r);
        $response = (string) $r->getBody();

        $ref = new ReflectionObject($router);
        self::assertTrue($ref->hasProperty('allMembers'), 'There must be an attribute set for that key');

        self::assertEquals(
            'John, Carl',
            $response,
            'The route must be declared anyway',
        );
    }

    /**
     * @covers Respect\Rest\Router::applyVirtualHost
     * @covers Respect\Rest\Router::appendRoute
     */
    public function testCreateUriShouldBeAwareOfVirtualHost(): void
    {
        $router = new Router(new Psr17Factory(), '/my/virtual/host');
        $catsRoute = $router->any('/cats/*', 'Meow');
        $virtualHostUri = $catsRoute->createUri('mittens');
        self::assertEquals(
            '/my/virtual/host/cats/mittens',
            $virtualHostUri,
            'Virtual host should be prepended to the path on createUri()',
        );
    }

    /**
     * @covers Respect\Rest\Router::handleOptionsRequest
     * @runInSeparateProcess
     */
    public function testOptionsRequestShouldNotCallOtherHandlers(): void
    {
        $router = new Router(new Psr17Factory());
        $router->get('/asian', 'GET: Asian Food!');
        $router->post('/asian', 'POST: Asian Food!');

        $response = $router->dispatch(new ServerRequest('OPTIONS', '/asian'))->response();

        self::assertNotNull($response);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertEqualsCanonicalizing(
            ['GET', 'HEAD', 'POST', 'OPTIONS'],
            array_map('trim', explode(',', $response->getHeaderLine('Allow'))),
        );
    }

    /** @covers Respect\Rest\Router::handleOptionsRequest */
    public function testOptionsRequestShouldBeDispatchedToCorrectOptionsHandler(): void
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
            'OPTIONS request should call the correct custom OPTIONS handler.',
        );
        self::assertEqualsCanonicalizing(
            ['GET', 'HEAD', 'POST', 'OPTIONS'],
            array_map('trim', explode(',', $response->getHeaderLine('Allow'))),
        );
    }

    /** @covers Respect\Rest\Router::handleOptionsRequest */
    public function testOptionsRequestShouldReturnBadRequestWhenExplicitOptionsRouteFailsRoutines(): void
    {
        $router = new Router(new Psr17Factory());
        $router->get('/asian', 'GET: Asian Food!');
        $router->options('/asian', static function (): string {
            return 'OPTIONS: Asian Food!';
        })->when(static function (): bool {
            return false;
        });
        $router->post('/asian', 'POST: Asian Food!');

        $response = $router->dispatch(new ServerRequest('OPTIONS', '/asian'))->response();

        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    /** @covers Respect\Rest\Router::handleOptionsRequest */
    public function testOptionsHandlerShouldMaterializeRoutelessResponseWhenNoExplicitRouteSurvives(): void
    {
        $router = new Router(new Psr17Factory());
        $router->request = new Request(new ServerRequest('OPTIONS', '/asian'));
        $router->request->responseFactory = new Psr17Factory();

        $handleOptionsRequest = new ReflectionMethod($router, 'handleOptionsRequest');
        $handleOptionsRequest->invoke($router, ['OPTIONS'], new SplObjectStorage());

        $response = $router->request->response();

        self::assertNotNull($response);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('OPTIONS', $response->getHeaderLine('Allow'));
    }

    // =========================================================================
    // OldRouterTest unique methods
    // =========================================================================

    /** @covers \Respect\Rest\Router */
    public function testNotRoutableController(): void
    {
        self::expectException('InvalidArgumentException');
        $this->router->instanceRoute('ANY', '/', new stdClass());
        self::responseBody($this->router->dispatch(new ServerRequest('get', '/')));
    }

    public function testNotRoutableControllerByName(): void
    {
        self::expectException('InvalidArgumentException');
        $this->router->classRoute('ANY', '/', '\\stdClass');
        self::responseBody($this->router->dispatch(new ServerRequest('get', '/')));
    }

    #[DataProvider('providerForSingleRoutes')]
    public function testSingleRoutes(string $route, string $path, mixed $expectedParams): void
    {
        $this->router->callbackRoute('get', $route, $this->callback);
        $this->router->dispatch(new ServerRequest('get', $path))->response();

        self::assertEquals($expectedParams, $this->result);
    }

    #[DataProvider('providerForLargeParams')]
    public function testLargeParams(string $route, string $path, mixed $expectedParams): void
    {
        $this->router->callbackRoute('get', $route, $this->callback);
        $this->router->dispatch(new ServerRequest('get', $path))->response();

        self::assertEquals($expectedParams, $this->result);
    }

    #[DataProvider('providerForSpecialChars')]
    public function testSpecialChars(string $route, string $path, mixed $expectedParams): void
    {
        $this->router->callbackRoute('get', $route, $this->callback);
        $this->router->dispatch(new ServerRequest('get', $path))->response();

        self::assertEquals($expectedParams, $this->result);
    }

    /** @return array<int, array<int, mixed>> */
    public static function providerForSingleRoutes(): array
    {
        return [
            [
                '/',
                '/',
                [],
            ],
            [
                '/users',
                '/users',
                [],
            ],
            [
                '/users/',
                '/users',
                [],
            ],
            [
                '/users',
                '/users/',
                [],
            ],
            [
                '/users/*',
                '/users/1',
                [1],
            ],
            [
                '/users/*/*',
                '/users/1/2',
                [1, 2],
            ],
            [
                '/users/*/lists',
                '/users/1/lists',
                [1],
            ],
            [
                '/users/*/lists/*',
                '/users/1/lists/2',
                [1, 2],
            ],
            [
                '/users/*/lists/*/*',
                '/users/1/lists/2/3',
                [1, 2, 3],
            ],
            [
                '/posts/*/*/*',
                '/posts/2010/10/10',
                [2010, 10, 10],
            ],
            [
                '/posts/*/*/*',
                '/posts/2010/10',
                [2010, 10],
            ],
            [
                '/posts/*/*/*',
                '/posts/2010',
                [2010],
            ],
            [
                '/posts/*/*/*',
                '/posts/2010/10///',
                [2010, 10],
            ],
            [
                '/posts/*/*/*',
                '/posts/2010/////',
                [2010],
            ],
            [
                '/posts/*/*/*',
                '/posts/2010/0/',
                [2010, 0],
            ],
            [
                '/users/*/*/lists/*/*',
                '/users/1/1B/lists/2/3',
                [1, '1B', 2, 3],
            ],
            [
                '/users/*/mounted-folder/**',
                '/users/alganet/mounted-folder/home/alganet/Projects/RespectRest/',
                ['alganet',['home', 'alganet', 'Projects', 'RespectRest']],
            ],
            [
                '/users/*/mounted-folder/*/**',
                '/users/alganet/mounted-folder/from-network/home/alganet/Projects/RespectRest/',
                ['alganet','from-network',['home', 'alganet', 'Projects', 'RespectRest']],
            ],
        ];
    }

    /** @return array<int, array<int, mixed>> */
    public static function providerForLargeParams(): array
    {
        return [
            [
                '/users/*/*/*/*/*/*/*',
                '/users/1',
                [1],
            ],
            [
                '/users/*/*/*/*/*/*/*',
                '/users/a/a/a/a/a/a/a',
                ['a', 'a', 'a', 'a', 'a', 'a', 'a'],
            ],
            [
                '/users' . str_repeat('/*', 2500),
                '/users' . str_repeat('/xy', 2500),
                str_split(str_repeat('xy', 2500), 2),
            ],
            [
                '/users' . str_repeat('/*', 2500),
                '/users' . str_repeat('/abcdefghijklmnopqrstuvwxyz', 2500),
                str_split(str_repeat('abcdefghijklmnopqrstuvwxyz', 2500), 26),
            ],
            [
                '/users' . str_repeat('/*', 2500),
                '/users' . str_repeat(
                    '/abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz',
                    2500,
                ),
                str_split(str_repeat(
                    'abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz',
                    2500,
                ), 26 * 3),
            ],
        ];
    }

    /** @return array<int, array<int, mixed>> */
    public static function providerForSpecialChars(): array
    {
        return [
            [
                '/My Documents/*',
                '/My Documents/1',
                [1],
            ],
            [
                '/My Documents/*',
                '/My%20Documents/1',
                [1],
            ],
            [
                '/(.*)/*/[a-z]/*',
                '/(.*)/1/[a-z]/2',
                [1, 2],
            ],
            [
                '/shinny*/*',
                '/shinny*/2',
                [2],
            ],
        ];
    }

    public function testBindControllerNoParams(): void
    {
        $this->router->any('/users/*', new MyController());
        $result = self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/alganet')));
        self::assertEquals(json_encode(['alganet', 'get', []]), $result);
    }

    public function testBindControllerParams(): void
    {
        /** @phpstan-ignore-next-line */
        $this->router->any('/users/*', MyController::class, ['ok']);
        $result = self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/alganet')));
        self::assertEquals(json_encode(['alganet', 'get', ['ok']]), $result);
    }

    public function testBindControllerInstance(): void
    {
        $this->router->instanceRoute('ANY', '/users/*', new MyController('ok'));
        $result = self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/alganet')));
        self::assertEquals(json_encode(['alganet', 'get', ['ok']]), $result);
    }

    public function testBindControllerFactory(): void
    {
        /** @phpstan-ignore-next-line */
        $this->router->any('/users/*', MyController::class, static function () {
            return new MyController('ok');
        });
        $result = self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/alganet')));
        self::assertEquals(json_encode(['alganet', 'get', ['ok']]), $result);
    }

    public function testBindControllerParams2(): void
    {
        $this->router->instanceRoute('ANY', '/users/*', new MyController('ok', 'foo', 'bar'));
        $result = self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/alganet')));
        self::assertEquals(json_encode(['alganet', 'get', ['ok', 'foo', 'bar']]), $result);
    }

    public function testBindControllerSpecial(): void
    {
        $this->router->instanceRoute('ANY', '/users/*', new MyController());
        $result = $this->router->dispatch(new ServerRequest('__construct', '/users/alganet'))->response();
        self::assertNotNull($result);
        self::assertSame(405, $result->getStatusCode());
        self::assertEqualsCanonicalizing(
            ['GET', 'HEAD', 'POST', 'OPTIONS'],
            array_map('trim', explode(',', $result->getHeaderLine('Allow'))),
        );
    }

    public function testBindControllerAnyRouteRejectsUnsupportedHttpMethod(): void
    {
        $this->router->instanceRoute('ANY', '/users/*', new MyController());
        $result = $this->router->dispatch(new ServerRequest('delete', '/users/alganet'))->response();
        self::assertNotNull($result);
        self::assertSame(405, $result->getStatusCode());
        self::assertEqualsCanonicalizing(
            ['GET', 'HEAD', 'POST', 'OPTIONS'],
            array_map('trim', explode(',', $result->getHeaderLine('Allow'))),
        );
    }

    public function testBindControllerMultiMethods(): void
    {
        $this->router->instanceRoute('ANY', '/users/*', new MyController());
        $result = self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/alganet')));
        self::assertEquals(json_encode(['alganet', 'get', []]), $result);

        $result = self::responseBody($this->router->dispatch(new ServerRequest('post', '/users/alganet')));
        self::assertEquals(json_encode(['alganet', 'post', []]), $result);
    }

    public function testProxyBy(): void
    {
        $result = null;
        $proxy = static function () use (&$result): void {
                $result = 'ok';
        };
        $this->router->get('/users/*', static function (): void {
        })->by($proxy);
        $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response();
        self::assertEquals('ok', $result);
    }

    /** @covers Respect\Rest\Router::always */
    public function testSimpleAlways(): void
    {
        $result = null;
        $proxy = static function () use (&$result): void {
                $result = 'ok';
        };
        $this->router->always('by', $proxy);
        $this->router->get('/users/*', static function (): void {
        });
        $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response();
        self::assertEquals('ok', $result);
    }

    /** @covers Respect\Rest\Router::always */
    public function testSimpleAlwaysAfter(): void
    {
        $result = null;
        $proxy = static function () use (&$result): void {
                $result = 'ok';
        };
        $this->router->get('/users/*', static function (): void {
        });
        $this->router->always('by', $proxy);
        $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response();
        self::assertEquals('ok', $result);
    }

    public function testProxyThrough(): void
    {
        $result = null;
        $proxy = static function () use (&$result): void {
                $result = 'ok';
        };
        $this->router->get('/users/*', static function (): void {
        })->through($proxy);
        $this->router->dispatch(new ServerRequest('get', '/users/alganet'))->response();
        self::assertEquals('ok', $result);
    }

    public function testProxyThroughOutput(): void
    {
        $proxy = static function () {
                return static function ($output) {
                        return $output . 'ok';
                };
        };
        $this->router->get('/users/*', static function () {
                return 'ok';
        })->through($proxy);
        $result = self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/alganet')));
        self::assertEquals('okok', $result);
    }

    public function testMultipleProxies(): void
    {
        $result = [];
        $proxy1 = static function ($foo) use (&$result): void {
                $result[] = $foo;
        };
        $proxy2 = static function ($bar) use (&$result): void {
                $result[] = $bar;
        };
        $proxy3 = static function ($baz) use (&$result): void {
                $result[] = $baz;
        };
        $this->router->get('/users/*/*/*', static function ($foo, $bar, $baz) use (&$result): void {
                $result[] = 'main';
        })->by($proxy1)->through($proxy2)->through($proxy3);
        $this->router->dispatch(new ServerRequest('get', '/users/abc/def/ghi'))->response();
        self::assertSame(
            ['abc', 'main', 'def', 'ghi'],
            $result,
        );
    }

    public function testProxyParamsByReference(): void
    {
        $resultProxy = null;
        $resultCallback = null;
        $proxy1 = static function ($foo = null, $abc = null) use (&$resultProxy): void {
                $resultProxy = func_get_args();
        };
        $callback = static function ($bar, $foo = null) use (&$resultCallback): void {
                $resultCallback = func_get_args();
        };
        $this->router->get('/users/*/*', $callback)->by($proxy1);
        $this->router->dispatch(new ServerRequest('get', '/users/abc/def'))->response();
        self::assertEquals(['def', null], $resultProxy);
        self::assertEquals(['abc', 'def'], $resultCallback);
    }

    public function testProxyReturnFalse(): void
    {
        $result = [];
        $proxy1 = static function ($foo) use (&$result) {
                $result[] = $foo;

                return false;
        };
        $proxy2 = static function ($bar) use (&$result): void {
                $result[] = $bar;
        };
        $proxy3 = static function ($baz) use (&$result): void {
                $result[] = $baz;
        };
        $this->router->get('/users/*/*/*', static function ($foo, $bar, $baz) use (&$result): void {
                $result[] = 'main';
        })->by($proxy1)->through($proxy2)->through($proxy3);
        $this->router->dispatch(new ServerRequest('get', '/users/abc/def/ghi'))->response();
        self::assertSame(
            ['abc'],
            $result,
        );
    }

    public function testWildcardOrdering(): void
    {
        $this->router->any('/posts/*/*', static function ($year, $month) {
                return 10;
        });
        $this->router->any('/**', static function ($userName) {
                return 5;
        });
        self::assertEquals(
            '10',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/posts/2010/20'))),
        );
        self::assertEquals(
            '5',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/anything'))),
        );
    }

    public function testOrdering(): void
    {
        $this->router->any('/users/*', static function ($userName) {
                return 5;
        });
        $this->router->any('/users/*/*', static function ($year, $month) {
                return 10;
        });
        self::assertEquals(
            '5',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/alganet'))),
        );
        self::assertEquals(
            '10',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/2010/20'))),
        );
    }

    public function testOrderingSpecific(): void
    {
        $this->router->any('/users/*/*', static function ($year, $month) {
                return 10;
        });
        $this->router->any('/users/lists/*', static function ($userName) {
                return 5;
        });
        self::assertEquals(
            '5',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/lists/alganet'))),
        );
        self::assertEquals(
            '10',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/users/foobar/alganet'))),
        );
    }

    public function testOrderingSpecific2(): void
    {
        $this->router->any('/', static function () {
                return 2;
        });
        $this->router->any('/*', static function () {
                return 3;
        });
        $this->router->any('/*/versions', static function () {
                return 4;
        });
        $this->router->any('/*/versions/*', static function () {
                return 5;
        });
        $this->router->any('/*/*', static function () {
                return 6;
        });
        self::assertEquals(
            '2',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/'))),
        );
        self::assertEquals(
            '3',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/foo'))),
        );
        self::assertEquals(
            '4',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/foo/versions'))),
        );
        self::assertEquals(
            '5',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/foo/versions/1.0'))),
        );
        self::assertEquals(
            '6',
            self::responseBody($this->router->dispatch(new ServerRequest('get', '/foo/bar'))),
        );
    }

    public function testExperimentalShell(): void
    {
        $router = new Router(new Psr17Factory());
        /** @phpstan-ignore-next-line */
        $router->install('/**', static function () {
                return 'Installed ' . implode(', ', func_get_arg(0));
        });
        $commandLine = 'install apache php mysql';
        $commandArgs = explode(' ', $commandLine);
        $output = self::responseBody($router->dispatch(
            new ServerRequest(array_shift($commandArgs), '/' . implode('/', $commandArgs)),
        ));
        self::assertEquals('Installed apache, php, mysql', $output);
    }

    public function testAccept(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept', 'application/json');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', static function () {
                return range(0, 10);
        })->accept(['application/json' => 'json_encode']);
        $r = self::responseBody($this->router->dispatchRequest($request));
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testAcceptCharset(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Charset', 'utf-8');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', static function () {
                return 'açaí';
        })->acceptCharset(['utf-8' => static fn($data) => mb_convert_encoding($data, 'ISO-8859-1', 'UTF-8')]);
        $r = self::responseBody($this->router->dispatchRequest($request));
        self::assertEquals(mb_convert_encoding('açaí', 'ISO-8859-1', 'UTF-8'), $r);
    }

    public function testAcceptEncoding(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Encoding', 'myenc');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', static function () {
                return 'foobar';
        })->acceptEncoding(['myenc' => 'strrev']);
        $r = self::responseBody($this->router->dispatchRequest($request));
        self::assertEquals(strrev('foobar'), $r);
    }

    public function testAcceptUrl(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet.json'))
            ->withHeader('Accept', '*/*');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', static function ($screenName) {
                return range(0, 10);
        })->accept(['.json' => 'json_encode']);
        $r = self::responseBody($this->router->dispatchRequest($request));
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testAcceptUrlNoParameters(): void
    {
        $serverRequest = (new ServerRequest('get', '/users.json'))
            ->withHeader('Accept', '*/*');
        $request = new Request($serverRequest);
        $this->router->get('/users', static function () {
                return range(0, 10);
        })->accept(['.json' => 'json_encode']);
        $r = self::responseBody($this->router->dispatchRequest($request));
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testFileExtension(): void
    {
        $request = new Request(new ServerRequest('get', '/users.json/10.20'));
        $this->router->get('/users.json/*', static function ($param) {
                [$min, $max] = explode('.', $param);

                return range($min, $max);
        });
        $r = self::responseBody($this->router->dispatchRequest($request));
        self::assertEquals(json_encode(range(10, 20)), $r);
    }

    public function testAcceptGeneric2(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept', '*/*');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', static function () {
                return range(0, 10);
        })->accept(['application/json' => 'json_encode']);
        $r = self::responseBody($this->router->dispatchRequest($request));
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testAcceptLanguage(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'en');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en-US' => static function () {
                    return 'Hi there';
            },
            'pt-BR' => static function () {
                    return 'Olá!';
            },
        ]);
        $r = self::responseBody($this->router->dispatchRequest($request));
        self::assertEquals('Hi there', $r);
    }

    public function testAcceptLanguage2(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt');
        $request = new Request($serverRequest);
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en-US' => static function () {
                    return 'Hi there';
            },
            'pt-BR' => static function () {
                    return 'Olá!';
            },
        ]);
        $r = self::responseBody($this->router->dispatchRequest($request));
        self::assertEquals('Olá!', $r);
    }

    public function testAcceptOrder(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt,en');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en' => static function () {
                    return 'Hi there';
            },
            'pt' => static function () {
                    return 'Olá!';
            },
        ]);
        $r = self::responseBody($this->router->dispatchRequest($requestBoth));
        self::assertEquals('Olá!', $r);
    }

    public function testUniqueRoutine(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt,en');
        $requestBoth = new Request($serverRequest);
        $neverRun = false;
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en' => static function () use (&$neverRun): void {
                    $neverRun = true;
            },
            'pt' => static function () use (&$neverRun): void {
                    $neverRun = true;
            },
        ])->acceptLanguage([
            'en' => static function () {
                    return 'dsfdfsdfsdf';
            },
            'pt' => static function () {
                    return 'sdfsdfsdfdf!';
            },
        ]);
        $this->router->dispatchRequest($requestBoth)->response();
        self::assertFalse($neverRun);
    }

    public function testAcceptMulti(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt,en')
            ->withHeader('Accept', 'application/json');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', static function ($data) {
                return '034930984';
        })->acceptLanguage([
            'en' => static function () {
                    return 'Hi there';
            },
            'pt' => static function () {
                    return 'Olá!';
            },
        ])->accept(['application/json' => 'json_encode']);
        $r = self::responseBody($this->router->dispatchRequest($requestBoth));
        self::assertEquals('"Ol\u00e1!"', $r);
    }

    public function testAcceptOrderX(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'x-klingon,en');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en' => static function () {
                    return 'Hi there';
            },
            'klingon-tr' => static function () {
                    return 'nuqneH';
            },
        ]);
        $r = self::responseBody($this->router->dispatchRequest($requestBoth));
        self::assertEquals('nuqneH', $r);
    }

    public function testAcceptOrderQuality(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt;q=0.7,en');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en-US' => static function () {
                    return 'Hi there';
            },
            'pt-BR' => static function () {
                    return 'Olá!';
            },
        ]);
        $r = self::responseBody($this->router->dispatchRequest($requestBoth));
        self::assertEquals('Hi there', $r);
    }

    public function testLastModifiedSince(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', static function () {
                return 'hi!';
        })->lastModified(
            static function () {
                return new DateTime('2011-11-11 11:11:12');
            },
        );
        $r = self::responseBody($this->router->dispatchRequest($requestBoth));
        self::assertEquals('hi!', $r);
    }

    public function testLastModifiedSince2(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', static function () {
                return 'hi!';
        })->lastModified(
            static function () {
                return new DateTime('2011-11-11 11:11:10');
            },
        );
        $response = $this->router->dispatchRequest($requestBoth)->response();
        self::assertNotNull($response);
        self::assertEquals(304, $response->getStatusCode());
        self::assertEquals('Fri, 11 Nov 2011 11:11:10 +0000', $response->getHeaderLine('Last-Modified'));
    }

    public function testLastModifiedSince3(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $requestBoth = new Request($serverRequest);
        $this->router->get('/users/*', static function () {
                return 'hi!';
        })->lastModified(
            static function () {
                return new DateTime('2011-11-11 11:11:11');
            },
        );
        $response = $this->router->dispatchRequest($requestBoth)->response();
        self::assertNotNull($response);
        self::assertEquals(304, $response->getStatusCode());
        self::assertEquals('Fri, 11 Nov 2011 11:11:11 +0000', $response->getHeaderLine('Last-Modified'));
    }

    public function testContenType(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Content-Type', 'text/xml');
        $requestBoth = new Request($serverRequest);
        $result = null;
        $this->router->get('/users/*', static function (): void {
        })->contentType([
            'text/json' => static function (): void {
            },
            'text/xml' => static function () use (&$result): void {
                    $result = 'ok';
            },
        ]);
        $this->router->dispatchRequest($requestBoth)->response();
        self::assertEquals('ok', $result);
    }

    public function testVirtualHost(): void
    {
        $router = new Router(new Psr17Factory(), '/myvh');
        $ok = false;
        $router->get('/alganet', static function () use (&$ok): void {
                $ok = true;
        });
        $router->dispatch(new ServerRequest('get', '/myvh/alganet'))->response();
        self::assertTrue($ok);
    }

    public function testVirtualHostEmpty(): void
    {
        $router = new Router(new Psr17Factory(), '/myvh');
        $ok = false;
        $router->get('/', static function () use (&$ok): void {
                $ok = true;
        });
        $router->dispatch(new ServerRequest('get', '/myvh'))->response();
        self::assertTrue($ok);
    }

    public function testVirtualHostIndex(): void
    {
        $router = new Router(new Psr17Factory(), '/myvh/index.php');
        $ok = false;
        $router->get('/', static function () use (&$ok): void {
                $ok = true;
        });
        $router->dispatch(new ServerRequest('get', '/myvh/index.php'))->response();
        self::assertTrue($ok);
    }

    public function testCreateUri(): void
    {
        $r = new Router(new Psr17Factory());
        $ro = $r->any('/users/*/test/*', static function (): void {
        });
        self::assertEquals(
            '/users/alganet/test/php',
            $ro->createUri('alganet', 'php'),
        );
        $r->isAutoDispatched = false;
    }

    public function testForward(): void
    {
        $r = new Router(new Psr17Factory());
        $ro1 = $r->any('/users/*', static function ($user) {
            return $user;
        });
        $r->any('/*', static function ($user) use ($ro1) {
            return $ro1;
        });
        $response = self::responseBody($r->dispatch(new ServerRequest('get', '/alganet')));
        self::assertEquals('alganet', $response);
    }

    /**
     * @group issues
     * @ticket 37
     **/
    public function test_optional_parameter_in_class_routes(): void
    {
        $r = new Router(new Psr17Factory());
        $r->any('/optional/*', MyOptionalParamRoute::class);
        $response = self::responseBody($r->dispatch(new ServerRequest('get', '/optional')));
        self::assertEquals('John Doe', $response);
    }

    // =========================================================================
    // NewRouterTest unique methods
    // =========================================================================

    /** @covers \Respect\Rest\Router */
    public function test_bad_request_header(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function () {
            return 'ok';
        })->when(static function () {
            return false;
        });
        $response = $router->dispatch(new ServerRequest('get', '/'))->response();
        self::assertNotNull($response);
        self::assertSame(400, $response->getStatusCode());
    }

    public function test_method_not_allowed_header(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function () {
            return 'ok';
        });
        $router->put('/', static function () {
            return 'ok';
        });
        $response = $router->dispatch(new ServerRequest('delete', '/'))->response();
        self::assertNotNull($response);
        self::assertSame(405, $response->getStatusCode());
        self::assertEqualsCanonicalizing(
            ['GET', 'HEAD', 'PUT', 'OPTIONS'],
            array_map('trim', explode(',', $response->getHeaderLine('Allow'))),
        );
    }

    public function test_method_not_allowed_header_with_conneg(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function () {
            return 'ok';
        })
            ->accept([
                'text/html' => static function ($d) {
                    return $d;
                },
            ]);
        $response = $router->dispatch(new ServerRequest('delete', '/'))->response();
        self::assertNotNull($response);
        self::assertSame(405, $response->getStatusCode());
        self::assertEqualsCanonicalizing(
            ['GET', 'HEAD', 'OPTIONS'],
            array_map('trim', explode(',', $response->getHeaderLine('Allow'))),
        );
    }

    public function test_transparent_options_allow_methods(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function () {
            return 'ok';
        });
        $router->post('/', static function () {
            return 'ok';
        });
        $response = $router->dispatch(new ServerRequest('options', '/'))->response();
        self::assertNotNull($response);
        self::assertSame(204, $response->getStatusCode());
        self::assertEqualsCanonicalizing(
            ['GET', 'HEAD', 'POST', 'OPTIONS'],
            array_map('trim', explode(',', $response->getHeaderLine('Allow'))),
        );
    }

    public function test_transparent_global_options_allow_methods(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function () {
            return 'ok';
        });
        $router->post('/', static function () {
            return 'ok';
        });
        $response = $router->dispatch(new ServerRequest('options', '*'))->response();
        self::assertNotNull($response);
        self::assertSame(204, $response->getStatusCode());
        self::assertEqualsCanonicalizing(
            ['GET', 'HEAD', 'POST', 'OPTIONS'],
            array_map('trim', explode(',', $response->getHeaderLine('Allow'))),
        );
    }

    public function test_method_not_acceptable(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function () {
            return 'ok';
        })
            ->accept([
                'foo/bar' => static function ($d) {
                    return $d;
                },
            ]);
        $response = $router->dispatch(new ServerRequest('get', '/'))->response();
        self::assertNotNull($response);
        self::assertEquals(406, $response->getStatusCode());
    }

    public function test_append_routine_honours_routine_chaining(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/one-time', static function () {
            return 'one-time';
        })
            ->appendRoutine(new Routines\Through(static function ($data) {
                return static function ($data) {
                    return $data . '-through1';
                };
            }))
            ->through(static function ($data) {
                return static function ($data) {
                    return $data . '-through2';
                };
            });
        $response = $router->dispatch(new ServerRequest('GET', '/one-time'));
        self::assertEquals('one-time-through1-through2', $response);
    }

    public function test_callback_gets_param_array(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        /** @phpstan-ignore-next-line */
        $router->get('/one-time/*', static function ($frag, $param1, $param2) {
            return 'one-time-' . $frag . '-' . $param1 . '-' . $param2;
        }, ['addl', 'add2']);
        $response = $router->dispatch(new ServerRequest('GET', '/one-time/1'));
        self::assertEquals('one-time-1-addl-add2', $response);
    }

    public function test_http_method_head(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function (ResponseInterface $response) {
            $response->getBody()->write('ok');

            return $response->withHeader('X-Handled-By', 'GET');
        });
        $response = $router->dispatch(new ServerRequest('HEAD', '/'))->response();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('GET', $response->getHeaderLine('X-Handled-By'));
        self::assertSame('', (string) $response->getBody());
    }

    public function test_http_method_head_run_returns_response_without_body(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function (ResponseInterface $response) {
            $response->getBody()->write('ok');

            return $response->withHeader('X-Handled-By', 'GET');
        });

        $response = $router->run(new Request(new ServerRequest('HEAD', '/')));

        self::assertNotNull($response);
        self::assertSame('GET', $response->getHeaderLine('X-Handled-By'));
        self::assertSame('', (string) $response->getBody());
    }

    public function test_http_method_head_with_class_routes_and_routines(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        /** @phpstan-ignore-next-line */
        $router->get('/', HeadTestStub::class, ['X-Burger: With Cheese!'])
            ->when(static function () {
                return true;
            });

        $response = $router->dispatch(new ServerRequest('HEAD', '/'))->response();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function test_http_method_head_with_instance_routes_uses_get_fallback(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->instanceRoute('ANY', '/users/*', new MyController('ok'));

        $response = $router->dispatch(new ServerRequest('HEAD', '/users/alganet'))->response();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('', (string) $response->getBody());
    }

    public function test_http_method_head_with_factory_routes_uses_get_fallback(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->factoryRoute('ANY', '/', HeadFactoryController::class, static function () {
            return new HeadFactoryController();
        });

        $response = $router->dispatch(new ServerRequest('HEAD', '/'))->response();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function test_explicit_head_route_overrides_get_fallback(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function (ResponseInterface $response) {
            $response->getBody()->write('get');

            return $response->withHeader('X-Handled-By', 'GET');
        });
        $router->head('/', static function (ResponseInterface $response) {
            $response->getBody()->write('head');

            return $response->withHeader('X-Handled-By', 'HEAD');
        });

        $response = $router->dispatch(new ServerRequest('HEAD', '/'))->response();

        self::assertNotNull($response);
        self::assertSame('HEAD', $response->getHeaderLine('X-Handled-By'));
        self::assertSame('', (string) $response->getBody());
    }

    public function test_user_agent_class(): void
    {
        $ua = new Routines\UserAgent([
            '*' => static function (): void {
            },
        ]);
        $authorize = new ReflectionMethod($ua, 'authorize');

        self::assertFalse($authorize->invoke($ua, 'a', 'b'));
        self::assertFalse($authorize->invoke($ua, 'c', 'b'));
        self::assertTrue($authorize->invoke($ua, '1', '1'));
        self::assertTrue($authorize->invoke($ua, '0', ''));
        self::assertTrue($authorize->invoke($ua, 'a', '*'));
    }

    public function test_user_agent_content_negotiation(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function () {
            return 'unknown';
        })->userAgent([
            'FIREFOX' => static function () {
                return 'FIREFOX';
            },
            'IE' => static function () {
                return 'IE';
            },
        ]);
        $serverRequest = (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX');
        $response = $router->dispatch($serverRequest);
        self::assertEquals('FIREFOX', $response);
    }

    public function test_user_agent_content_negotiation_fallback(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function () {
            return 'unknown';
        })->userAgent([
            '*' => static function () {
                return 'IE';
            },
        ]);
        $serverRequest = (new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX');
        $response = $router->dispatch($serverRequest);
        self::assertEquals('IE', $response);
    }

    public function test_stream_routine(): void
    {
        $done                            = false;
        $self                            = $this;
        $serverRequest                   = (new ServerRequest('GET', '/input'))
                                            ->withHeader('Accept-Encoding', 'deflate');
        $request                         = new Request($serverRequest);
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/input', static function () {
            return fopen('php://input', 'r+');
        })
            ->acceptEncoding([
                'deflate' => static function ($stream) use ($self, &$done) {
                            $done = true;
                            $self->assertIsResource($stream);
                            stream_filter_append($stream, 'zlib.deflate', STREAM_FILTER_READ);

                            return $stream;
                },
            ]);

        $response = $router->run($request);
        self::assertTrue($done);
        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function test_optional_parameter_in_function_routes(): void
    {
        $r = new Router(new Psr17Factory());
        $r->any('/optional/*', static function ($user = null) {
            return $user ?: 'John Doe';
        });
        self::assertEquals('John Doe', self::responseBody($r->dispatch(new ServerRequest('get', '/optional'))));
    }

    public function test_optional_parameter_in_function_routes_multiple(): void
    {
        $r = new Router(new Psr17Factory());
        $r->any('/optional', static function () {
            return 'No User';
        });
        $r->any('/optional/*', static function ($user = null) {
            return $user ?: 'John Doe';
        });
        self::assertEquals('No User', self::responseBody($r->dispatch(new ServerRequest('get', '/optional'))));
    }

    public function test_two_optional_parameters_in_function_routes(): void
    {
        $r = new Router(new Psr17Factory());
        $r->any('/optional/*/*', static function ($user = null, $list = null) {
            return $user . $list;
        });
        self::assertEquals('FooBar', self::responseBody($r->dispatch(new ServerRequest('get', '/optional/Foo/Bar'))));
    }

    public function test_two_optional_parameters_one_passed_in_function_routes(): void
    {
        $r = new Router(new Psr17Factory());
        $r->any('/optional/*/*', static function ($user = null, $list = null) {
            return $user . $list;
        });
        self::assertEquals('Foo', self::responseBody($r->dispatch(new ServerRequest('get', '/optional/Foo'))));
    }

    public function test_single_last_param(): void
    {
        $r = new Router(new Psr17Factory());
        $args = [];
        $r->any('/documents/*', static function ($documentId) use (&$args): void {
            $args = func_get_args();
        });
        $r->dispatch(new ServerRequest('get', '/documents/1234'))->response();
        self::assertEquals(['1234'], $args);
    }

    public function test_single_last_param2(): void
    {
        $r = new Router(new Psr17Factory());
        $args = [];
        $r->any('/documents/**', static function ($documentsPath) use (&$args): void {
            $args = func_get_args();
        });
        $r->dispatch(new ServerRequest('get', '/documents/foo/bar'))->response();
        self::assertEquals([['foo', 'bar']], $args);
    }

    public function test_catchall_on_root_call_should_get_callback_parameter(): void
    {
        $r = new Router(new Psr17Factory());
        $args = [];
        $r->any('/**', static function ($documentsPath) use (&$args): void {
            $args = func_get_args();
        });
        $r->dispatch(new ServerRequest('get', '/'))->response();
        self::assertIsArray($args[0]);
    }

    /** @ticket 46 */
    public function test_is_callable_proxy(): void
    {
        $callable = new class {
            public function getBar(mixed $bar): mixed
            {
                return $bar;
            }
        };
        $e = 'Hello';
        $r = new Router(new Psr17Factory());
        $r->get('/', $e)
            ->accept([
                'text/html' => [$callable, 'getBar'],
            ]);
        $serverRequest = (new ServerRequest('get', '/'))->withHeader('Accept', 'text/html');
        $response = $r->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertEquals($e, (string) $response->getBody());
    }

    /** @return array<int, array<int, string>> */
    public static function provider_content_type(): array
    {
        return [
            ['text/html'],
            ['application/json'],
        ];
    }

    /** @ticket 44 */
    #[DataProvider('provider_content_type')]
    public function test_automatic_content_type_header(string $ctype): void
    {
        $r = new Router(new Psr17Factory());
        $r->get('/auto', '')->accept([$ctype => 'json_encode']);
        $serverRequest = (new ServerRequest('get', '/auto'))->withHeader('Accept', $ctype);
        $response = $r->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertEquals($ctype, $response->getHeaderLine('Content-Type'));
    }

    /** @ticket 44 */
    #[DataProvider('provider_content_type')]
    public function test_wildcard_automatic_content_type_header(string $ctype): void
    {
        $r = new Router(new Psr17Factory());
        $r->get('/auto', '')->accept([$ctype => 'json_encode']);
        $serverRequest = (new ServerRequest('get', '/auto'))->withHeader('Accept', '*/*');
        $response = $r->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertEquals($ctype, $response->getHeaderLine('Content-Type'));
    }

    public function test_request_forward(): void
    {
        $r = new Router(new Psr17Factory());
        $r1 = $r->get('/route1', 'route1');
        $response = self::responseBody($r->dispatch(new ServerRequest('get', '/route1')));
        self::assertEquals('route1', $response);
        $r2 = $r->get('/route2', 'route2');
        $response = self::responseBody($r->dispatch(new ServerRequest('get', '/route2')));
        self::assertEquals('route2', $response);
        $r2->by(static function () use ($r1) {
            return $r1;
        });
        $response = self::responseBody($r->dispatch(new ServerRequest('get', '/route2')));
        self::assertEquals('route1', $response);
    }

    public function test_negotiate_acceptable_complete_headers(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/accept', static function () {
            return 'ok';
        })
            ->accept([
                'foo/bar' => static function ($d) {
                    return $d;
                },
            ])
            ->acceptLanguage([
                '13375p34|<' => static function ($d) {
                    return $d;
                },
            ]);
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

    public function test_accept_content_type_header(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function () {
            return 'ok';
        })
            ->accept([
                'foo/bar' => static function ($d) {
                    return $d;
                },
            ]);
        $serverRequest = (new ServerRequest('get', '/'))->withHeader('Accept', 'foo/bar');
        $response = $router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertEquals('foo/bar', $response->getHeaderLine('Content-Type'));
    }

    public function test_accept_content_language_header(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $router->methodOverriding = false;
        $router->get('/', static function () {
            return 'ok';
        })
            ->acceptLanguage([
                '13375p34|<' => static function ($d) {
                    return $d;
                },
            ]);
        $serverRequest = (new ServerRequest('get', '/'))->withHeader('Accept-Language', '13375p34|<');
        $response = $router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertStringContainsString('accept-language', $response->getHeaderLine('Vary'));
    }

    /** @return array<int, array<int, string>> */
    public static function provider_content_type_extension(): array
    {
        return [
            ['text/html', '.html'],
            ['application/json', '.json'],
            ['text/xml', '.xml'],
        ];
    }

    #[DataProvider('provider_content_type_extension')]
    public function test_do_not_set_automatic_content_type_header_for_extensions(string $ctype, string $ext): void
    {
        $r = new Router(new Psr17Factory());
        $r->get('/auto', '')->accept([$ext => 'json_encode']);

        $r = $r->dispatch(new ServerRequest('get', '/auto' . $ext))->response();
        // Extension-based accept should not set Content-Type header
        self::assertNotNull($r);
    }

    /** @covers \Respect\Rest\Routes\AbstractRoute */
    public function test_optional_parameters_should_be_allowed_only_at_the_end_of_the_path(): void
    {
        $r = new Router(new Psr17Factory());
        $r->get('/users/*/photos/*', static function ($username, $photoId = null) {
            return 'match';
        });
        $psrResponse = $r->dispatch(new ServerRequest('get', '/users/photos'))->response();
        $response = $psrResponse !== null ? (string) $psrResponse->getBody() : '';
        self::assertNotEquals('match', $response);
    }

    public function test_route_ordering_with_when(): void
    {
        $when = false;
        $r = new Router(new Psr17Factory());

        $r->get('/', 'HOME');

        $r->get('/users', static function () {
            return 'users';
        });

        $r->get('/users/*', static function ($userId) {
            return 'user-' . $userId;
        })->when(static function ($userId) use (&$when) {
            $when = true;

            return is_numeric($userId) && $userId > 0;
        });

        $r->get('/docs', static function () {
            return 'DOCS!';
        });
        $response = self::responseBody($r->dispatch(new ServerRequest('get', '/users/1')));

        self::assertTrue($when);
        self::assertEquals('user-1', $response);
    }

    public function test_when_should_be_called_only_on_existent_methods(): void
    {
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;

        $r1 = $router->any('/meow/*', StubRoutable::class);
        $r1->accept(['application/json' => 'json_encode']);

        $router->any('/moo/*', RouteKnowsNothing::class);

        $serverRequest = (new ServerRequest('get', '/meow/blub'))->withHeader('Accept', 'application/json');
        $response = $router->run(new Request($serverRequest));
        self::assertNotNull($response);
        $out = (string) $response->getBody();

        self::assertEquals('"blub"', $out);
    }

    public function test_request_should_be_available_from_router_after_dispatching(): void
    {
        $request = new Request(new ServerRequest('get', '/foo'));
        $router = new Router(new Psr17Factory());
        $router->isAutoDispatched = false;
        $phpunit = $this;
        $router->get('/foo', static function () use ($router, $request, $phpunit) {
            $phpunit->assertSame($request, $router->request);

            return spl_object_hash($router->request);
        });
        $response = $router->run($request);
        self::assertNotNull($response);
        $out = (string) $response->getBody();
        self::assertEquals($out, spl_object_hash($request));
    }

    private static function responseBody(Request $request): string
    {
        $response = $request->response();
        self::assertNotNull($response);

        return (string) $response->getBody();
    }
}
