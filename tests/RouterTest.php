<?php

declare(strict_types=1);

namespace Respect\Rest\Test;

use DateTime;
use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionMethod;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routable;
use Respect\Rest\Router;
use Respect\Rest\Routes\AbstractRoute;
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
use function json_decode;
use function json_encode;
use function mb_convert_encoding;
use function range;
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
        $this->router = self::newRouter();
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
        $router = self::newRouter();
        /** @phpstan-ignore-next-line */
        $router->thisIsInsufficientForMagicConstruction();
    }

    /** @covers Respect\Rest\Router::__call */
    public function testMagicConstructorWarnsIfNoSufficientParametersWerePassed2(): void
    {
        self::expectException('InvalidArgumentException');
        $router = self::newRouter();
        /** @phpstan-ignore-next-line */
        $router->thisIsInsufficientForMagicConstruction('/magicians');
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::callbackRoute
     */
    public function testMagicConstructorCanCreateCallbackRoutes(): void
    {
        $router = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
        $nonStaticRoute = $router->get('/', $staticValue);

        $router->dispatchContext(self::newContextForRouter($router, new ServerRequest('GET', '/')))->response();

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
        $router = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
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
     * @covers Respect\Rest\Router::dispatchContext
     * @covers Respect\Rest\DispatchEngine::dispatchContext
     * @covers Respect\Rest\DispatchEngine::isRoutelessDispatch
     * @covers Respect\Rest\DispatchEngine::isDispatchedToGlobalOptionsMethod
     * @covers Respect\Rest\DispatchEngine::getAllowedMethods
     * @runInSeparateProcess
     */
    public function testCanRespondToGlobalOptionsMethodAutomatically(): void
    {
        $router = self::newRouter();
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
     * @covers Respect\Rest\Router::dispatchContext
     * @covers Respect\Rest\DispatchEngine::dispatchContext
     * @covers Respect\Rest\DispatchEngine::isRoutelessDispatch
     * @covers Respect\Rest\DispatchEngine::isDispatchedToGlobalOptionsMethod
     */
    public function testGlobalOptionsMethodWithoutRoutesReturns404(): void
    {
        $router = self::newRouter();

        $response = $router->dispatch(new ServerRequest('OPTIONS', '*'))->response();

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @covers Respect\Rest\Router::dispatchContext
     * @covers Respect\Rest\DispatchEngine::dispatchContext
     * @covers Respect\Rest\DispatchEngine::isRoutelessDispatch
     */
    public function testPostRequestDoesNotOverrideMethodFromRequestBody(): void
    {
        $router = self::newRouter();
        $router->put('/bulbs', 'Some Bulbs Put Response');
        $router->post('/bulbs', 'Some Bulbs Post Response');

        $serverRequest = (new ServerRequest('POST', '/bulbs'))->withParsedBody(['_method' => 'PUT']);
        $response = $router->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        $result = (string) $response->getBody();

        self::assertSame(
            'Some Bulbs Post Response',
            $result,
            'Router should dispatch to POST instead of overriding the method from request data',
        );

        self::assertNotSame(
            'Some Bulbs Put Response',
            $result,
            'Router should not dispatch to PUT based on request body method overrides',
        );
    }

    /**
     * @covers  Respect\Rest\Router::dispatchContext
     * @covers  Respect\Rest\Router::routeDispatch
     * @covers  Respect\Rest\Router::applyBasePath
     */
    public function testDeveloperCanSetUpABasePathOnConstructor(): void
    {
        $router = self::newRouter('/store');
        $router->get('/products', 'Some Products!');
        $r = $router->dispatch(new ServerRequest('GET', '/store/products'))->response();
        self::assertNotNull($r);
        $response = (string) $r->getBody();

        self::assertSame(
            'Some Products!',
            $response,
            'Router should match using the base path combined URI',
        );
    }

    /**
     * @covers Respect\Rest\Router::dispatch
     * @covers Respect\Rest\DispatchEngine::dispatch
     * @covers Respect\Rest\DispatchEngine::routeDispatch
     */
    public function testReturns404WhenNoRoutesExist(): void
    {
        $router = self::newRouter();
        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
    }

    /**
     * @covers Respect\Rest\Router::dispatch
     * @covers Respect\Rest\DispatchEngine::dispatch
     * @covers Respect\Rest\DispatchEngine::routeDispatch
     */
    public function testReturns404WhenNoRouteMatches(): void
    {
        $router = self::newRouter();
        $router->get('/foo', 'This exists.');
        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNotNull($response);
        self::assertSame(404, $response->getStatusCode());
    }

    /** @covers Respect\Rest\Router::appendRoute */
    public function testNamesRoutesUsingAttributes(): void
    {
        $router = self::newRouter();
        $allMembers = $router->any('/members', 'John, Carl');
        self::assertInstanceOf(AbstractRoute::class, $allMembers);

        $r = $router->dispatch(new ServerRequest('GET', '/members'))->response();
        self::assertNotNull($r);
        $response = (string) $r->getBody();

        self::assertEquals(
            'John, Carl',
            $response,
            'The route must be declared anyway',
        );
    }

    /**
     * @covers Respect\Rest\DispatchEngine::applyBasePath
     * @covers Respect\Rest\Router::appendRoute
     */
    public function testCreateUriShouldBeAwareOfBasePath(): void
    {
        $router = self::newRouter('/my/virtual/host');
        $catsRoute = $router->any('/cats/*', 'Meow');
        $basePathUri = $catsRoute->createUri('mittens');
        self::assertEquals(
            '/my/virtual/host/cats/mittens',
            $basePathUri,
            'Base path should be prepended to the path on createUri()',
        );
    }

    /**
     * @covers Respect\Rest\DispatchEngine::handleOptionsRequest
     * @runInSeparateProcess
     */
    public function testOptionsRequestShouldNotCallOtherHandlers(): void
    {
        $router = self::newRouter();
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

    /** @covers Respect\Rest\DispatchEngine::handleOptionsRequest */
    public function testOptionsRequestShouldBeDispatchedToCorrectOptionsHandler(): void
    {
        $router = self::newRouter();
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

    /** @covers Respect\Rest\DispatchEngine::handleOptionsRequest */
    public function testOptionsRequestShouldReturnBadRequestWhenExplicitOptionsRouteFailsRoutines(): void
    {
        $router = self::newRouter();
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

    /** @covers Respect\Rest\DispatchEngine::handleOptionsRequest */
    public function testOptionsHandlerShouldMaterializeRoutelessResponseWhenNoExplicitRouteSurvives(): void
    {
        $router = self::newRouter();
        $context = self::newContextForRouter($router, new ServerRequest('OPTIONS', '/asian'));

        $handleOptionsRequest = new ReflectionMethod($router->dispatchEngine(), 'handleOptionsRequest');
        $handleOptionsRequest->invoke($router->dispatchEngine(), $context, ['OPTIONS'], new SplObjectStorage());

        $response = $context->response();

        self::assertNotNull($response);
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('OPTIONS', $response->getHeaderLine('Allow'));
    }

    // =========================================================================
    // OldRouterTest unique methods
    // =========================================================================

    /**
     * A controller (stdClass) with no HTTP method handlers produces 500
     * because the route path matches but no method can be dispatched.
     *
     * @covers \Respect\Rest\Router
     */
    public function testNotRoutableControllerReturns500(): void
    {
        $this->router->instanceRoute('ANY', '/', new stdClass());
        $response = $this->router->handle(new ServerRequest('GET', '/'));
        self::assertSame(500, $response->getStatusCode());
    }

    public function testNotRoutableControllerByNameReturns500(): void
    {
        $this->router->classRoute('ANY', '/', '\\stdClass');
        $response = $this->router->handle(new ServerRequest('GET', '/'));
        self::assertSame(500, $response->getStatusCode());
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
            ['abc', 'main', 'abc', 'abc'],
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
        self::assertEquals(['abc', 'def'], $resultProxy);
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
        $router = self::newRouter();
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
        $request = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function () {
                return range(0, 10);
        })->accept(['application/json' => 'json_encode']);
        $r = self::responseBody($this->router->dispatchContext($request));
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testAcceptCharset(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Charset', 'utf-8');
        $request = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function () {
                return 'açaí';
        })->acceptCharset(['utf-8' => static fn($data) => mb_convert_encoding($data, 'ISO-8859-1', 'UTF-8')]);
        $r = self::responseBody($this->router->dispatchContext($request));
        self::assertEquals(mb_convert_encoding('açaí', 'ISO-8859-1', 'UTF-8'), $r);
    }

    public function testAcceptEncoding(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Encoding', 'myenc');
        $request = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function () {
                return 'foobar';
        })->acceptEncoding(['myenc' => 'strrev']);
        $r = self::responseBody($this->router->dispatchContext($request));
        self::assertEquals(strrev('foobar'), $r);
    }

    public function testFileExtensionUrl(): void
    {
        $serverRequest = new ServerRequest('get', '/users/alganet.json');
        $request = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function ($screenName) {
                return range(0, 10);
        })->fileExtension(['.json' => 'json_encode']);
        $r = self::responseBody($this->router->dispatchContext($request));
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testFileExtensionUrlNoParameters(): void
    {
        $serverRequest = new ServerRequest('get', '/users.json');
        $request = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users', static function () {
                return range(0, 10);
        })->fileExtension(['.json' => 'json_encode']);
        $r = self::responseBody($this->router->dispatchContext($request));
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testFileExtension(): void
    {
        $request = self::newContextForRouter($this->router, new ServerRequest('get', '/users.json/10.20'));
        $this->router->get('/users.json/*', static function ($param) {
                [$min, $max] = explode('.', $param);

                return range($min, $max);
        });
        $r = self::responseBody($this->router->dispatchContext($request));
        self::assertEquals(json_encode(range(10, 20)), $r);
    }

    public function testFileExtensionCascading(): void
    {
        $translateEn = static function ($d) {
            return $d . ':en';
        };
        $encodeJson = static function ($d) {
            return '{' . $d . '}';
        };

        $router = self::newRouter();
        $router->get('/page/*', static function (string $slug) {
            return $slug;
        })
            ->fileExtension(['.en' => $translateEn, '.pt' => $translateEn])
            ->fileExtension(['.json' => $encodeJson, '.html' => $encodeJson]);

        $response = $router->dispatch(new ServerRequest('GET', '/page/about.json.en'))->response();
        self::assertNotNull($response);
        self::assertSame('{about:en}', (string) $response->getBody());
    }

    public function testFileExtensionLenientUnknownExtension(): void
    {
        $router = self::newRouter();
        $router->get('/users/*', static function (string $name) {
            return $name;
        })->fileExtension(['.json' => 'json_encode']);

        $response = $router->dispatch(new ServerRequest('GET', '/users/john.doe'))->response();
        self::assertNotNull($response);
        self::assertSame('john.doe', (string) $response->getBody());
    }

    public function testFileExtensionNoExtensionInUrl(): void
    {
        $router = self::newRouter();
        $router->get('/users/*', static function (string $name) {
            return $name;
        })->fileExtension(['.json' => 'json_encode']);

        $response = $router->dispatch(new ServerRequest('GET', '/users/alganet'))->response();
        self::assertNotNull($response);
        self::assertSame('alganet', (string) $response->getBody());
    }

    public function testAcceptGeneric2(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept', '*/*');
        $request = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function () {
                return range(0, 10);
        })->accept(['application/json' => 'json_encode']);
        $r = self::responseBody($this->router->dispatchContext($request));
        self::assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testAcceptLanguage(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'en');
        $request = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en-US' => static function () {
                    return 'Hi there';
            },
            'pt-BR' => static function () {
                    return 'Olá!';
            },
        ]);
        $r = self::responseBody($this->router->dispatchContext($request));
        self::assertEquals('Hi there', $r);
    }

    public function testAcceptLanguage2(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt');
        $request = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en-US' => static function () {
                    return 'Hi there';
            },
            'pt-BR' => static function () {
                    return 'Olá!';
            },
        ]);
        $r = self::responseBody($this->router->dispatchContext($request));
        self::assertEquals('Olá!', $r);
    }

    public function testAcceptOrder(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt,en');
        $requestBoth = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en' => static function () {
                    return 'Hi there';
            },
            'pt' => static function () {
                    return 'Olá!';
            },
        ]);
        $r = self::responseBody($this->router->dispatchContext($requestBoth));
        self::assertEquals('Olá!', $r);
    }

    public function testUniqueRoutine(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt,en');
        $requestBoth = self::newContextForRouter($this->router, $serverRequest);
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
        $this->router->dispatchContext($requestBoth)->response();
        self::assertFalse($neverRun);
    }

    public function testAcceptMulti(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt,en')
            ->withHeader('Accept', 'application/json');
        $requestBoth = self::newContextForRouter($this->router, $serverRequest);
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
        $r = self::responseBody($this->router->dispatchContext($requestBoth));
        self::assertEquals('"Ol\u00e1!"', $r);
    }

    public function testAcceptOrderX(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'x-klingon,en');
        $requestBoth = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en' => static function () {
                    return 'Hi there';
            },
            'klingon-tr' => static function () {
                    return 'nuqneH';
            },
        ]);
        $r = self::responseBody($this->router->dispatchContext($requestBoth));
        self::assertEquals('nuqneH', $r);
    }

    public function testAcceptOrderQuality(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Accept-Language', 'pt;q=0.7,en');
        $requestBoth = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function (): void {
        })->acceptLanguage([
            'en-US' => static function () {
                    return 'Hi there';
            },
            'pt-BR' => static function () {
                    return 'Olá!';
            },
        ]);
        $r = self::responseBody($this->router->dispatchContext($requestBoth));
        self::assertEquals('Hi there', $r);
    }

    public function testLastModifiedSince(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $requestBoth = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function () {
                return 'hi!';
        })->lastModified(
            static function () {
                return new DateTime('2011-11-11 11:11:12');
            },
        );
        $r = self::responseBody($this->router->dispatchContext($requestBoth));
        self::assertEquals('hi!', $r);
    }

    public function testLastModifiedSince2(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $requestBoth = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function () {
                return 'hi!';
        })->lastModified(
            static function () {
                return new DateTime('2011-11-11 11:11:10');
            },
        );
        $response = $this->router->dispatchContext($requestBoth)->response();
        self::assertNotNull($response);
        self::assertEquals(304, $response->getStatusCode());
        self::assertEquals('Fri, 11 Nov 2011 11:11:10 +0000', $response->getHeaderLine('Last-Modified'));
    }

    public function testLastModifiedSince3(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('If-Modified-Since', '2011-11-11 11:11:11');
        $requestBoth = self::newContextForRouter($this->router, $serverRequest);
        $this->router->get('/users/*', static function () {
                return 'hi!';
        })->lastModified(
            static function () {
                return new DateTime('2011-11-11 11:11:11');
            },
        );
        $response = $this->router->dispatchContext($requestBoth)->response();
        self::assertNotNull($response);
        self::assertEquals(304, $response->getStatusCode());
        self::assertEquals('Fri, 11 Nov 2011 11:11:11 +0000', $response->getHeaderLine('Last-Modified'));
    }

    public function testContenType(): void
    {
        $serverRequest = (new ServerRequest('get', '/users/alganet'))
            ->withHeader('Content-Type', 'text/xml');
        $requestBoth = self::newContextForRouter($this->router, $serverRequest);
        $result = null;
        $this->router->get('/users/*', static function (): void {
        })->contentType([
            'text/json' => static function (): void {
            },
            'text/xml' => static function () use (&$result): void {
                    $result = 'ok';
            },
        ]);
        $this->router->dispatchContext($requestBoth)->response();
        self::assertEquals('ok', $result);
    }

    public function testContentTypeDecodesPayloadIntoServerRequest(): void
    {
        $factory = new Psr17Factory();
        $serverRequest = (new ServerRequest('post', '/timeline'))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($factory->createStream('{"user":"alice"}'));

        $this->router
            ->post('/timeline', static function (ServerRequestInterface $request) {
                return json_encode([
                    'parsed' => $request->getParsedBody(),
                    'attribute' => $request->getAttribute(Routines\ContentType::ATTRIBUTE),
                ]);
            })
            ->contentType([
                'application/json' => static function (string $input): array {
                    return json_decode($input, true);
                },
            ]);

        $psrResponse = $this->router->dispatch($serverRequest)->response();

        self::assertNotNull($psrResponse);
        self::assertSame(
            json_encode([
                'parsed' => ['user' => 'alice'],
                'attribute' => ['user' => 'alice'],
            ]),
            (string) $psrResponse->getBody(),
        );
    }

    public function testContentTypeRejectsUnsupportedMediaType(): void
    {
        $factory = new Psr17Factory();
        $ran = false;
        $serverRequest = (new ServerRequest('post', '/timeline'))
            ->withHeader('Content-Type', 'text/xml')
            ->withBody($factory->createStream('<user>alice</user>'));

        $this->router
            ->post('/timeline', static function () use (&$ran) {
                $ran = true;

                return 'ok';
            })
            ->contentType([
                'application/json' => static function (string $input): array {
                    return json_decode($input, true);
                },
            ]);

        $response = $this->router->dispatch($serverRequest)->response();

        self::assertNotNull($response);
        self::assertSame(415, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
        self::assertFalse($ran);
    }

    public function testContentTypeMatchesParameterizedMediaType(): void
    {
        $factory = new Psr17Factory();
        $serverRequest = (new ServerRequest('post', '/timeline'))
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withBody($factory->createStream('{"user":"alice"}'));

        $this->router
            ->post('/timeline', static function (ServerRequestInterface $request) {
                return json_encode($request->getParsedBody());
            })
            ->contentType([
                'application/json' => static function (string $input): array {
                    return json_decode($input, true);
                },
            ]);

        $response = $this->router->dispatch($serverRequest)->response();

        self::assertNotNull($response);
        self::assertSame(json_encode(['user' => 'alice']), (string) $response->getBody());
    }

    public function testContentTypeDoesNotLeakUnsupportedStatusIntoLaterMatch(): void
    {
        $factory = new Psr17Factory();
        $serverRequest = (new ServerRequest('post', '/timeline'))
            ->withHeader('Content-Type', 'text/xml')
            ->withBody($factory->createStream('<user>alice</user>'));

        $this->router
            ->post('/timeline', static function () {
                return 'json';
            })
            ->contentType([
                'application/json' => static function (string $input): array {
                    return json_decode($input, true);
                },
            ]);

        $this->router
            ->post('/timeline', static function (ServerRequestInterface $request) {
                return (string) $request->getAttribute(Routines\ContentType::ATTRIBUTE);
            })
            ->contentType([
                'text/xml' => static function (string $input): string {
                    return $input;
                },
            ]);

        $response = $this->router->dispatch($serverRequest)->response();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('<user>alice</user>', (string) $response->getBody());
    }

    public function testBasePath(): void
    {
        $router = self::newRouter('/myvh');
        $ok = false;
        $router->get('/alganet', static function () use (&$ok): void {
                $ok = true;
        });
        $router->dispatch(new ServerRequest('get', '/myvh/alganet'))->response();
        self::assertTrue($ok);
    }

    public function testBasePathEmpty(): void
    {
        $router = self::newRouter('/myvh');
        $ok = false;
        $router->get('/', static function () use (&$ok): void {
                $ok = true;
        });
        $router->dispatch(new ServerRequest('get', '/myvh'))->response();
        self::assertTrue($ok);
    }

    public function testBasePathIndex(): void
    {
        $router = self::newRouter('/myvh/index.php');
        $ok = false;
        $router->get('/', static function () use (&$ok): void {
                $ok = true;
        });
        $router->dispatch(new ServerRequest('get', '/myvh/index.php'))->response();
        self::assertTrue($ok);
    }

    public function testBasePathSlashMeansRoot(): void
    {
        $router = self::newRouter('/');
        $ok = false;
        $router->get('/hello', static function () use (&$ok): void {
                $ok = true;
        });
        $router->dispatch(new ServerRequest('get', '/hello'))->response();
        self::assertTrue($ok);
    }

    public function testBasePathTrailingSlashNormalized(): void
    {
        $router = self::newRouter('/myapp/');
        $ok = false;
        $router->get('/test', static function () use (&$ok): void {
                $ok = true;
        });
        $router->dispatch(new ServerRequest('get', '/myapp/test'))->response();
        self::assertTrue($ok);
    }

    public function testCreateUri(): void
    {
        $r = self::newRouter();
        $ro = $r->any('/users/*/test/*', static function (): void {
        });
        self::assertEquals(
            '/users/alganet/test/php',
            $ro->createUri('alganet', 'php'),
        );
    }

    public function testForward(): void
    {
        $r = self::newRouter();
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
        $r = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
        $router->get('/', static function () {
            return 'ok';
        })
            ->accept([
                'foo/bar' => static function ($d) {
                    return $d;
                },
            ]);
        $response = $router->dispatch(
            (new ServerRequest('get', '/'))->withHeader('Accept', 'text/plain'),
        )->response();
        self::assertNotNull($response);
        self::assertEquals(406, $response->getStatusCode());
    }

    public function test_missing_accept_header_is_permissive(): void
    {
        $router = self::newRouter();
        $router->get('/', static function () {
            return range(0, 2);
        })->accept(['application/json' => 'json_encode']);

        $response = $router->dispatch(new ServerRequest('get', '/'))->response();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(json_encode(range(0, 2)), (string) $response->getBody());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function test_accept_matches_media_type_with_parameters(): void
    {
        $router = self::newRouter();
        $router->get('/', static function () {
            return range(0, 2);
        })->accept(['application/json' => 'json_encode']);

        $response = $router->dispatch(
            (new ServerRequest('get', '/'))->withHeader('Accept', 'application/json; charset=utf-8'),
        )->response();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(json_encode(range(0, 2)), (string) $response->getBody());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function test_append_routine_honours_routine_chaining(): void
    {
        $router = self::newRouter();
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
        $router = self::newRouter();
        /** @phpstan-ignore-next-line */
        $router->get('/one-time/*', static function ($frag, $param1, $param2) {
            return 'one-time-' . $frag . '-' . $param1 . '-' . $param2;
        }, ['addl', 'add2']);
        $response = $router->dispatch(new ServerRequest('GET', '/one-time/1'));
        self::assertEquals('one-time-1-addl-add2', $response);
    }

    public function test_http_method_head(): void
    {
        $router = self::newRouter();
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
        $router = self::newRouter();
        $router->get('/', static function (ResponseInterface $response) {
            $response->getBody()->write('ok');

            return $response->withHeader('X-Handled-By', 'GET');
        });

        $response = $router->dispatchContext(
            self::newContextForRouter($router, new ServerRequest('HEAD', '/')),
        )->response();

        self::assertNotNull($response);
        self::assertSame('GET', $response->getHeaderLine('X-Handled-By'));
        self::assertSame('', (string) $response->getBody());
    }

    public function testDispatchEngineImplementsRequestHandlerInterface(): void
    {
        self::assertInstanceOf(RequestHandlerInterface::class, $this->router->dispatchEngine());
    }

    public function testDispatchEngineHandleReturnsSameResponseAsDispatch(): void
    {
        $router = self::newRouter();
        $router->get('/', static function () {
            return 'handled';
        });

        $request = new ServerRequest('GET', '/');
        $dispatched = $router->dispatch($request)->response();
        $handled = $router->dispatchEngine()->handle($request);

        self::assertNotNull($dispatched);
        self::assertSame($dispatched->getStatusCode(), $handled->getStatusCode());
        self::assertSame((string) $dispatched->getBody(), (string) $handled->getBody());
    }

    public function testDispatchEngineHandlePropagatesUncaughtExceptions(): void
    {
        $router = self::newRouter();
        $router->get('/', static function (): void {
            throw new InvalidArgumentException('boom');
        });

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('boom');
        $router->dispatchEngine()->handle(new ServerRequest('GET', '/'));
    }

    public function testDispatchEngineHandlePreservesExceptionRoutes(): void
    {
        $router = self::newRouter();
        $router->get('/', static function (): void {
            throw new InvalidArgumentException('boom');
        });
        $router->onException(InvalidArgumentException::class, static function () {
            return 'caught';
        });

        $response = $router->dispatchEngine()->handle(new ServerRequest('GET', '/'));

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('caught', (string) $response->getBody());
    }

    public function testExceptionRouteRespectsGlobalAcceptRoutine(): void
    {
        $router = self::newRouter();
        $router->always('Accept', [
            'application/json' => static function ($data) {
                return json_encode(['error' => $data]);
            },
        ]);
        $router->get('/', static function (): never {
            throw new InvalidArgumentException('boom');
        });
        $router->onException(InvalidArgumentException::class, static function (InvalidArgumentException $e) {
            return $e->getMessage();
        });

        $request = (new ServerRequest('GET', '/'))->withHeader('Accept', 'application/json');
        $response = $router->dispatch($request)->response();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('{"error":"boom"}', (string) $response->getBody());
    }

    public function testExceptionRouteReturningEmptyStringDoesNotRethrow(): void
    {
        $router = self::newRouter();
        $router->get('/', static function (): never {
            throw new InvalidArgumentException('boom');
        });
        $router->onException(InvalidArgumentException::class, static function () {
            return '';
        });

        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('', (string) $response->getBody());
    }

    public function testRouterImplementsMiddlewareInterface(): void
    {
        self::assertInstanceOf(MiddlewareInterface::class, $this->router);
    }

    public function testRouterProcessHandlesRequestWithoutDelegating(): void
    {
        $router = self::newRouter();
        $router->get('/', static function () {
            return 'processed';
        });
        $handler = new class implements RequestHandlerInterface {
            public bool $wasCalled = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->wasCalled = true;

                return (new Psr17Factory())->createResponse(418);
            }
        };

        $response = $router->process(new ServerRequest('GET', '/'), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('processed', (string) $response->getBody());
        self::assertFalse($handler->wasCalled);
    }

    public function testRouterProcessDelegatesToHandlerOn404(): void
    {
        $router = self::newRouter();
        $router->get('/exists', static function () {
            return 'found';
        });
        $handler = new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $factory = new Psr17Factory();

                return $factory->createResponse(200)->withBody(
                    $factory->createStream('from handler'),
                );
            }
        };

        $response = $router->process(new ServerRequest('GET', '/not-found'), $handler);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('from handler', (string) $response->getBody());
    }

    public function testRouterProcessDoesNotDelegateOn405(): void
    {
        $router = self::newRouter();
        $router->get('/resource', static function () {
            return 'get only';
        });
        $handler = new class implements RequestHandlerInterface {
            public bool $wasCalled = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->wasCalled = true;

                return (new Psr17Factory())->createResponse(200);
            }
        };

        $response = $router->process(new ServerRequest('DELETE', '/resource'), $handler);

        self::assertSame(405, $response->getStatusCode());
        self::assertFalse($handler->wasCalled);
    }

    public function testRouterProcessDoesNotDelegateWhenRouteReturns404(): void
    {
        $router = self::newRouter();
        $router->get('/check', static function () {
            return (new Psr17Factory())->createResponse(404)
                ->withBody((new Psr17Factory())->createStream('not here'));
        });
        $handler = new class implements RequestHandlerInterface {
            public bool $wasCalled = false;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->wasCalled = true;

                return (new Psr17Factory())->createResponse(200);
            }
        };

        $response = $router->process(new ServerRequest('GET', '/check'), $handler);

        self::assertSame(404, $response->getStatusCode());
        self::assertSame('not here', (string) $response->getBody());
        self::assertFalse($handler->wasCalled);
    }

    public function test_http_method_head_with_class_routes_and_routines(): void
    {
        $router = self::newRouter();
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
        $router = self::newRouter();
        $router->instanceRoute('ANY', '/users/*', new MyController('ok'));

        $response = $router->dispatch(new ServerRequest('HEAD', '/users/alganet'))->response();

        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('', (string) $response->getBody());
    }

    public function test_http_method_head_with_factory_routes_uses_get_fallback(): void
    {
        $router = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
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
        $router = self::newRouter();
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

    public function test_user_agent_can_block_before_handler_runs(): void
    {
        $router = self::newRouter();
        $ran = false;
        $router->get('/', static function () use (&$ran) {
            $ran = true;

            return 'unknown';
        })->userAgent([
            'FIREFOX' => static function (): bool {
                return false;
            },
        ]);

        $response = $router->dispatch((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'))->response();

        self::assertNotNull($response);
        self::assertSame('', (string) $response->getBody());
        self::assertFalse($ran);
    }

    public function test_user_agent_single_routine_can_run_before_and_after_route(): void
    {
        $router = self::newRouter();
        $calls = [];
        $router->get('/', static function () use (&$calls) {
            $calls[] = 'route';

            return 'unknown';
        })->userAgent([
            'FIREFOX' => static function () use (&$calls) {
                $calls[] = 'by';

                return static function (string $output) use (&$calls): string {
                    $calls[] = 'through';

                    return $output . '-FIREFOX';
                };
            },
        ]);

        $response = $router->dispatch((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'))->response();

        self::assertNotNull($response);
        self::assertSame('unknown-FIREFOX', (string) $response->getBody());
        self::assertSame(['by', 'route', 'through'], $calls);
    }

    public function test_user_agent_with_response_transformer_signature_skips_pre_route_execution(): void
    {
        $router = self::newRouter();
        $calls = [];
        $router->get('/', static function () use (&$calls) {
            $calls[] = 'route';

            return 'unknown';
        })->userAgent([
            'FIREFOX' => static function (string $output) use (&$calls): string {
                $calls[] = 'through';

                return $output . '-FIREFOX';
            },
        ]);

        $response = $router->dispatch((new ServerRequest('GET', '/'))->withHeader('User-Agent', 'FIREFOX'))->response();

        self::assertNotNull($response);
        self::assertSame('unknown-FIREFOX', (string) $response->getBody());
        self::assertSame(['route', 'through'], $calls);
    }

    public function test_stream_routine(): void
    {
        $done                            = false;
        $self                            = $this;
        $router = self::newRouter();
        $serverRequest                   = (new ServerRequest('GET', '/input'))
                                            ->withHeader('Accept-Encoding', 'deflate');
        $request                         = self::newContextForRouter($router, $serverRequest);
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

        $response = $router->dispatchContext($request)->response();
        self::assertTrue($done);
        self::assertInstanceOf(ResponseInterface::class, $response);
    }

    public function test_optional_parameter_in_function_routes(): void
    {
        $r = self::newRouter();
        $r->any('/optional/*', static function ($user = null) {
            return $user ?: 'John Doe';
        });
        self::assertEquals('John Doe', self::responseBody($r->dispatch(new ServerRequest('get', '/optional'))));
    }

    public function test_optional_parameter_in_function_routes_multiple(): void
    {
        $r = self::newRouter();
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
        $r = self::newRouter();
        $r->any('/optional/*/*', static function ($user = null, $list = null) {
            return $user . $list;
        });
        self::assertEquals('FooBar', self::responseBody($r->dispatch(new ServerRequest('get', '/optional/Foo/Bar'))));
    }

    public function test_two_optional_parameters_one_passed_in_function_routes(): void
    {
        $r = self::newRouter();
        $r->any('/optional/*/*', static function ($user = null, $list = null) {
            return $user . $list;
        });
        self::assertEquals('Foo', self::responseBody($r->dispatch(new ServerRequest('get', '/optional/Foo'))));
    }

    public function test_single_last_param(): void
    {
        $r = self::newRouter();
        $args = [];
        $r->any('/documents/*', static function ($documentId) use (&$args): void {
            $args = func_get_args();
        });
        $r->dispatch(new ServerRequest('get', '/documents/1234'))->response();
        self::assertEquals(['1234'], $args);
    }

    public function test_single_last_param2(): void
    {
        $r = self::newRouter();
        $args = [];
        $r->any('/documents/**', static function ($documentsPath) use (&$args): void {
            $args = func_get_args();
        });
        $r->dispatch(new ServerRequest('get', '/documents/foo/bar'))->response();
        self::assertEquals([['foo', 'bar']], $args);
    }

    public function test_catchall_on_root_call_should_get_callback_parameter(): void
    {
        $r = self::newRouter();
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
        $r = self::newRouter();
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
        $r = self::newRouter();
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
        $r = self::newRouter();
        $r->get('/auto', '')->accept([$ctype => 'json_encode']);
        $serverRequest = (new ServerRequest('get', '/auto'))->withHeader('Accept', '*/*');
        $response = $r->dispatch($serverRequest)->response();
        self::assertNotNull($response);
        self::assertEquals($ctype, $response->getHeaderLine('Content-Type'));
    }

    public function test_request_forward(): void
    {
        $r = self::newRouter();
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
        $router = self::newRouter();
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
        self::assertStringContainsString('accept', $response->getHeaderLine('Vary'));
        self::assertStringContainsString('accept-language', $response->getHeaderLine('Vary'));
        self::assertEquals('/accept', $response->getHeaderLine('Content-Location'));
        self::assertSame('', $response->getHeaderLine('Expires'));
        self::assertSame('', $response->getHeaderLine('Cache-Control'));
    }

    public function test_negotiate_merges_vary_headers(): void
    {
        $router = self::newRouter();
        $router->get('/', static function () {
            return 'ok';
        })
            ->accept([
                'foo/bar' => static function ($data) {
                    return $data;
                },
            ])
            ->acceptLanguage([
                'en' => static function ($data) {
                    return $data;
                },
            ]);

        $response = $router->dispatch(
            (new ServerRequest('get', '/'))
                ->withHeader('Accept', 'foo/bar')
                ->withHeader('Accept-Language', 'en'),
        )->response();

        self::assertNotNull($response);
        self::assertEqualsCanonicalizing(
            ['negotiate', 'accept', 'accept-language'],
            array_map('trim', explode(',', $response->getHeaderLine('Vary'))),
        );
    }

    public function test_negotiate_respects_existing_response_headers(): void
    {
        $router = self::newRouter();
        $router->get('/', static function (ResponseInterface $response) {
            $response->getBody()->write('ok');

            return $response
                ->withHeader('Vary', 'origin')
                ->withHeader('Content-Location', '/custom')
                ->withHeader('Cache-Control', 'public, max-age=60');
        })
            ->acceptLanguage([
                'en' => static function ($data) {
                    return $data;
                },
            ]);

        $response = $router->dispatch(
            (new ServerRequest('get', '/'))->withHeader('Accept-Language', 'en'),
        )->response();

        self::assertNotNull($response);
        self::assertEqualsCanonicalizing(
            ['origin', 'negotiate', 'accept-language'],
            array_map('trim', explode(',', $response->getHeaderLine('Vary'))),
        );
        self::assertSame('/custom', $response->getHeaderLine('Content-Location'));
        self::assertSame('public, max-age=60', $response->getHeaderLine('Cache-Control'));
        self::assertSame('', $response->getHeaderLine('Expires'));
    }

    public function test_accept_content_type_header(): void
    {
        $router = self::newRouter();
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
        $router = self::newRouter();
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
    public function test_file_extension_does_not_set_content_type_header(string $ctype, string $ext): void
    {
        $r = self::newRouter();
        $r->get('/auto', '')->fileExtension([$ext => 'json_encode']);

        $response = $r->dispatch(new ServerRequest('get', '/auto' . $ext))->response();
        self::assertNotNull($response);
        self::assertFalse($response->hasHeader('Content-Type'));
    }

    /** @covers \Respect\Rest\Routes\AbstractRoute */
    public function test_optional_parameters_should_be_allowed_only_at_the_end_of_the_path(): void
    {
        $r = self::newRouter();
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
        $r = self::newRouter();

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
        $router = self::newRouter();

        $r1 = $router->any('/meow/*', StubRoutable::class);
        $r1->accept(['application/json' => 'json_encode']);

        $router->any('/moo/*', RouteKnowsNothing::class);

        $serverRequest = (new ServerRequest('get', '/meow/blub'))->withHeader('Accept', 'application/json');
        $response = $router->dispatchContext(self::newContextForRouter($router, $serverRequest))->response();
        self::assertNotNull($response);
        $out = (string) $response->getBody();

        self::assertEquals('"blub"', $out);
    }

    public function test_dispatch_context_is_passed_through_to_route_callback(): void
    {
        $router = self::newRouter();
        $context = self::newContextForRouter($router, new ServerRequest('get', '/foo'));
        $router->get('/foo', static fn() => 'dispatched');
        $response = $router->dispatchContext($context)->response();
        self::assertNotNull($response);
        self::assertSame('dispatched', (string) $response->getBody());
    }

    private static function responseBody(DispatchContext $request): string
    {
        $response = $request->response();
        self::assertNotNull($response);

        return (string) $response->getBody();
    }

    private static function newRouter(string $basePath = ''): Router
    {
        return new Router($basePath, new Psr17Factory());
    }

    private static function newContextForRouter(Router $router, ServerRequestInterface $serverRequest): DispatchContext
    {
        return $router->createDispatchContext($serverRequest);
    }
}
