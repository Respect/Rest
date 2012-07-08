<?php
namespace Respect\Rest;

use PHPUnit_Framework_TestCase;

/**
 * @covers Respect\Rest\Router
 */
class RouterTest extends PHPUnit_Framework_TestCase
{
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
            'Respect\Rest\Routes\Callback', 
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
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::callbackRoute
     * @depends testMagicConstructorCanCreateCallbackRoutes
     */
    public function testMagicConstructorCanCreateCallbackRoutesWithExtraParams()
    {
        $router = new Router;
        $callbackRoute = $router->get('/', $target = function() {}, array('extra'));
        $concreteCallbackRoute = $router->callbackRoute('GET', '/', $target, array('extra'));

        $this->assertInstanceOf(
            'Respect\Rest\Routes\Callback', 
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
        $myInstance = $this->getMockForAbstractClass('Respect\Rest\Routable');
        $instanceRoute = $router->get('/', $myInstance);
        $concreteInstanceRoute = $router->instanceRoute('GET', '/', $myInstance);

        $this->assertInstanceOf(
            'Respect\Rest\Routes\Instance', 
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
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::staticRoute
     * @dataProvider provideForStaticRoutableValues
     */
    public function testMagicConstructorCanRouteToStaticValue($staticValue, $reason)
    {
        $router = new Router;
        $staticRoute = $router->get('/', $staticValue);
        $concreteStaticRoute = $router->staticRoute('GET','/', $staticValue);

        $this->assertInstanceOf(
            'Respect\Rest\Routes\StaticValue', 
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
     * @covers Respect\Rest\Router::__call
     * @covers Respect\Rest\Router::staticRoute
     * @dataProvider provideForNonStaticRoutableValues
     */
    public function testMagicConstructorCannotRouteSomeStaticValues($staticValue, $reason)
    {
        $router = new Router;
        $nonStaticRoute = $router->get('/', $staticValue);

        $this->assertNotInstanceOf(
            'Respect\Rest\Routes\StaticValue', 
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
        eval("class $className implements Respect\Rest\Routable{}");
        $classRoute = $router->get('/', $className);
        $concreteClassRoute = $router->classRoute('GET', '/', $className);

        $this->assertInstanceOf(
            'Respect\Rest\Routes\ClassName', 
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
        eval("class $className implements Respect\Rest\Routable{}");
        $classRoute = $router->get('/', $className, array('some', 'constructor', 'params'));
        $concreteClassRoute = $router->classRoute('GET', '/', $className, array('some', 'constructor', 'params'));

        $this->assertInstanceOf(
            'Respect\Rest\Routes\ClassName', 
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
        $factoryRoute = $router->get('/', 'DateTime', array('DateTime', 'createFromFormat'));
        $concreteFactoryRoute = $router->factoryRoute('GET', '/', 'DateTime', array('DateTime', 'createFromFormat'));

        $this->assertInstanceOf(
            'Respect\Rest\Routes\Factory', 
            $factoryRoute,
            'Returned result from a magic constructor in this case should return a Routes\Factory'
        );
        $this->assertEquals(
            $factoryRoute, 
            $concreteFactoryRoute,
            'The magic and concrete instances of Routes\Factory should be equivalent'
        );
    }
}