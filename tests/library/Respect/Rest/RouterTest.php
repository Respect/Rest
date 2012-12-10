<?php
namespace Respect\Rest;

use PHPUnit_Framework_TestCase;
/**
 * @covers Respect\Rest\Router
 */
class RouterTest extends PHPUnit_Framework_TestCase
{
    public static $status = 200;

    public function setUp()
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/';
    }

    /**
     * @covers            Respect\Rest\Router::__call
     * @expectedException InvalidArgumentException
     */
    public function testMagicConstructorWarnsIfNoSufficientParametersWerePassed()
    {
        $router = new Router;
        $router->thisIsInsufficientForMagicConstruction();
    }
    /**
     * @covers            Respect\Rest\Router::__call
     * @expectedException InvalidArgumentException
     */
    public function testMagicConstructorWarnsIfNoSufficientParametersWerePassed2()
    {
        $router = new Router;
        $router->thisIsInsufficientForMagicConstruction('/magicians');
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::callbackRoute
     */
    public function testMagicConstructorCanCreateCallbackRoutes()
    {
        $router = new Router;
        $callbackRoute = $router->get('/', $target = function() {});
        $concreteCallbackRoute = $router->callbackRoute('GET', '/', $target);

        $this->assertInstanceOf(
            'Respect\\Rest\\Routes\\Callback',
            $callbackRoute,
            'Returned result from a magic constructor in this case should return a Routes\Callback'
        );

        $this->assertEmpty(
            $callbackRoute->arguments,
            'When there are no arguments the Routes\Callback should have none as well'
        );

        $this->assertEquals(
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
        $router = new Router;
        $callbackRoute = $router->get('/', $target = function() {}, array('extra'));
        $concreteCallbackRoute = $router->callbackRoute('GET', '/', $target, array('extra'));

        $this->assertInstanceOf(
            'Respect\\Rest\\Routes\\Callback',
            $callbackRoute,
            'Returned result from a magic constructor in this case should return a Routes\Callback'
        );

        $this->assertContains(
            'extra',
            $callbackRoute->arguments,
            'The "extra" appended to the magic constructor should be present on the arguments list'
        );

        $this->assertEquals(
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
        $router = new Router;
        $myInstance = $this->getMock('Respect\\Rest\\Routable', array('GET'));
        $instanceRoute = $router->get('/', $myInstance);
        $concreteInstanceRoute = $router->instanceRoute('GET', '/', $myInstance);

        $this->assertInstanceOf(
            'Respect\\Rest\\Routes\\Instance',
            $instanceRoute,
            'Returned result from a magic constructor in this case should return a Routes\Instance'
        );

        $this->assertEquals(
            $instanceRoute,
            $concreteInstanceRoute,
            'The magic and concrete instances of Routes\Instance should be equivalent'
        );
    }

    /**
     * @covers       Respect\Rest\Router::__call
     * @covers       Respect\Rest\Router::staticRoute
     * @dataProvider provideForStaticRoutableValues
     */
    public function testMagicConstructorCanRouteToStaticValue($staticValue, $reason)
    {
        $router = new Router;
        $router->isAutoDispatched = false; // prevent static content from being echoed on dispatch
        $staticRoute = $router->get('/', $staticValue);
        $concreteStaticRoute = $router->staticRoute('GET','/', $staticValue);

        $this->assertInstanceOf(
            'Respect\\Rest\\Routes\\StaticValue',
            $staticRoute,
            $reason
        );

        $this->assertEquals(
            $staticRoute,
            $concreteStaticRoute,
            'The magic and concrete instances of Routes\Static should be equivalent'
        );
    }

    public function provideForStaticRoutableValues()
    {
        return array(
            array('Some Static Value', 'Strings should be possible to route statically'),
            array(array('Some', 'Other', 'Routable', 'Value'), 'Arrays should be possible to route statically'),
            array(10, 'Integers and scalars should be possible to route statically')
        );
    }

    /**
     * @covers            Respect\Rest\Router::__call
     * @covers            Respect\Rest\Router::staticRoute
     * @dataProvider      provideForNonStaticRoutableValues
     * @expectedException InvalidArgumentException
     */
    public function testMagicConstructorCannotRouteSomeStaticValues($staticValue, $reason)
    {
        $router = new Router;
        $nonStaticRoute = $router->get('/', $staticValue);

        $router->run(); // __toString is not allowed to throw exceptions

        $this->assertNotInstanceOf(
            'Respect\\Rest\\Routes\\StaticValue',
            $nonStaticRoute,
            $reason
        );
    }

    public function provideForNonStaticRoutableValues()
    {
        return array(
            array('PDO', 'Strings that are class names should NOT be possible to route statically'),
            array('Traversable', 'Strings that are interface names should NOT be possible to route statically')
        );
    }

    /**
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::classRoute
     */
    public function testMagicConstructorCanRouteToClasses()
    {
        $router = new Router;
        $className = 'GeneratedClass'.md5(rand());
        $this->getMock('Respect\\Rest\\Routable', array('GET'), array(), $className);
        $classRoute = $router->get('/', $className);
        $concreteClassRoute = $router->classRoute('GET', '/', $className);

        $this->assertInstanceOf(
            'Respect\\Rest\\Routes\\ClassName',
            $classRoute,
            'Returned result from a magic constructor in this case should return a Routes\ClassName'
        );

        $this->assertEquals(
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
        $router = new Router;
        $className = 'GeneratedClass'.md5(rand());
        $this->getMock('Respect\\Rest\\Routable', array('GET'), array(), $className);
        $classRoute = $router->get('/', $className, array('some', 'constructor', 'params'));
        $concreteClassRoute = $router->classRoute('GET', '/', $className, array('some', 'constructor', 'params'));

        $this->assertInstanceOf(
            'Respect\\Rest\\Routes\\ClassName',
            $classRoute,
            'Returned result from a magic constructor in this case should return a Routes\ClassName'
        );

        $this->assertEquals(
            array('some', 'constructor', 'params'),
            $classRoute->constructorParams,
            'The constructor params should be available on the instance of Routes\ClassName'
        );

        $this->assertEquals(
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
        $router = new Router;
        eval('class MockRoutable implements Respect\Rest\Routable{ public function GET() {} }');
        eval('class FactoryClass { public static function factoryMethod() { return new MockRoutable(); } }');
        $factoryRoute = $router->get('/', 'FactoryClass', array('FactoryClass', 'factoryMethod'));
        $concreteFactoryRoute = $router->factoryRoute('GET', '/', 'FactoryClass', array('FactoryClass', 'factoryMethod'));

        $this->assertInstanceOf(
            'Respect\\Rest\\Routes\\Factory',
            $factoryRoute,
            'Returned result from a magic constructor in this case should return a Routes\Factory'
        );

        $this->assertEquals(
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
        $router = new Router;
        $router->get('/asian', 'Asian Food!');
        $router->post('/eastern', 'Eastern Food!');
        $router->eat('/mongolian', 'Mongolian Food!');
        $response = (string) $router->dispatch('OPTIONS', '*')->response();

        $this->assertContains(
            'Allow: GET, POST, EAT',
            xdebug_get_headers(),
            'There should be a sent Allow header with all methods from all routes'
        );
    }

    /**
     * @covers Respect\Rest\Router::dispatchRequest
     * @covers Respect\Rest\Router::isRoutelessDispatch
     * @covers Respect\Rest\Router::hasDispatchedOverridenMethod
     */
    public function testDeveloperCanOverridePostMethodWithQueryStringParameter()
    {
        $_REQUEST['_method'] = 'PUT';
        $router = new Router;
        $router->methodOverriding = true;
        $router->put('/bulbs', 'Some Bulbs Put Response');
        $router->post('/bulbs', 'Some Bulbs Post Response');

        $result = (string) $router->dispatch('POST', '/bulbs')->response();

        $this->assertSame(
            'Some Bulbs Put Response',
            $result,
            'Router should dispatch to PUT (overriden) instead of POST'
        );

        $this->assertNotSame(
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
     * @depends testDeveloperCanOverridePostMethodWithQueryStringParameter
     */
    public function testDeveloperCanTurnOffMethodOverriding(Router $router)
    {
        $_REQUEST['_method'] = 'PUT';
        $router->methodOverriding = false;
        $result = (string) $router->dispatch('POST', '/bulbs')->response();

        $this->assertSame(
            'Some Bulbs Post Response',
            $result,
            'Router should dispatch to POST (not overriden) instead of PUT'
        );

        $this->assertNotSame(
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
        $router = new Router('/store');
        $router->get('/products', 'Some Products!');
        $response = $router->dispatch('GET', '/store/products')->response();

        $this->assertSame(
            'Some Products!',
            $response,
            'Router should match using the virtual host combined URI'
        );
    }

    /**
     * @covers Respect\Rest\Router::__destruct
     */
    public function testRouterCanBeAutoDispatchedIfProtocolIsDefined()
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $router = new Router;
        $router->get('/', 'Hello Respect');
        unset($router);

        $this->expectOutputString('Hello Respect');
    }

    /**
     * @covers Respect\Rest\Router::dispatch
     * @covers Respect\Rest\Router::routeDispatch
     */
    public function testReturns404WhenNoRoutesExist()
    {
        $router = new Router;
        $response = (string) $router->dispatch('GET', '/')->response();

        $this->assertEquals(
            '404',
            static::$status,
            'There should be a sent 404 status'
        );
    }

    /**
     * @covers Respect\Rest\Router::dispatch
     * @covers Respect\Rest\Router::routeDispatch
     */
    public function testReturns404WhenNoRouteMatches()
    {
        $router = new Router;
        $router->get('/foo', 'This exists.');
        $response = (string) $router->dispatch('GET', '/')->response();

        $this->assertEquals(
            '404',
            static::$status,
            'There should be a sent 404 status'
        );
    }

    /**
     * @covers Respect\Rest\Router::appendRoute
     */
    public function testNamesRoutesUsingAttributes()
    {
        $router = new Router;
        $router->allMembers = $router->any('/members', 'John, Carl');
        $response = (string) $router->dispatch('GET', '/members')->response();

        $this->assertTrue(
            isset($router->allMembers),
            'There must be an attribute set for that key'
        );

        $this->assertEquals(
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
        $router = new Router('/my/virtual/host');
        $catsRoute = $router->any('/cats/*', 'Meow');
        $virtualHostUri = $catsRoute->createUri('mittens');
        $this->assertEquals(
            '/my/virtual/host/cats/mittens',
            $virtualHostUri,
            'Virtual host should be prepended to the path on createUri()'
        );
    }

}

if (!function_exists(__NAMESPACE__.'\\header')) {
    function header($h) {
        $s = debug_backtrace(true);
        $rt = function($a) {return isset($a['object'])
            && $a['object'] instanceof RouterTest;};
        if (array_filter($s, $rt) && 0 === strpos($h, 'HTTP/1.1 ')) {
            RouterTest::$status = substr($h, 9);
        }
        return @\header($h);
    }
}
