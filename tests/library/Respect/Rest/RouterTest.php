<?php
declare(strict_types=1);

namespace Respect\Rest;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
/**
 * @covers Respect\Rest\Router
 */
final class RouterTest extends TestCase
{
    /**
     * @covers            Respect\Rest\Router::__call
     */
    public function testMagicConstructorWarnsIfNoSufficientParametersWerePassed()
    {
        self::expectException('InvalidArgumentException');
        $router = new Router(new Psr17Factory());
        $router->thisIsInsufficientForMagicConstruction();
    }
    /**
     * @covers            Respect\Rest\Router::__call
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
        $router->isAutoDispatched = false; // prevent static content from being echoed on dispatch
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

        $router->run(new Request(new ServerRequest('GET', '/'))); // __toString is not allowed to throw exceptions

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
        eval('class MockRoutable implements Respect\Rest\Routable{ public function GET() {} }');
        eval('class FactoryClass { public static function factoryMethod() { return new MockRoutable(); } }');
        $factoryRoute = $router->get('/', 'FactoryClass', ['FactoryClass', 'factoryMethod']);
        $concreteFactoryRoute = $router->factoryRoute('GET', '/', 'FactoryClass', ['FactoryClass', 'factoryMethod']);

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

        // Global OPTIONS returns null route — no response body
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
        // arrange
        $router = new Router(new Psr17Factory());
        $router->get('/asian', 'GET: Asian Food!');
        $router->post('/asian', 'POST: Asian Food!');

        // act
        $response = $router->dispatch(new ServerRequest('OPTIONS', '/asian'))->response();

        // assert: OPTIONS without explicit OPTIONS handler returns null route
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
        // arrange
        $router = new Router(new Psr17Factory());
        $router->get('/asian', 'GET: Asian Food!');
        $router->options('/asian', 'OPTIONS: Asian Food!');
        $router->post('/asian', 'POST: Asian Food!');

        // act
        $response = $router->dispatch(new ServerRequest('OPTIONS', '/asian'))->response();

        // assert: explicit OPTIONS handler should be dispatched
        self::assertNotNull($response);
        self::assertEquals(
            'OPTIONS: Asian Food!',
            (string) $response->getBody(),
            'OPTIONS request should call the correct custom OPTIONS handler.'
        );
    }

}

class StubRoutable implements Routable {
    public function GET() { return 'stub response'; }
}
