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
     */
    public function testMagicConstructorCanCreateCallbackRoutes()
    {
        $router = new Router;
        $callbackRoute = $router->get('/', function() {});

        $this->assertInstanceOf(
            'Respect\Rest\Routes\Callback', 
            $callbackRoute,
            'Returned result from a magic constructor in this case should return a Routes\Callback'
        );
        $this->assertEmpty(
            $callbackRoute->arguments,
            'When there are no arguments the Routes\Callback should have none as well'
        );
    }

    /**
     * @covers  Respect\Rest\Router::__call
     * @depends testMagicConstructorCanCreateCallbackRoutes
     */
    public function testMagicConstructorCanCreateCallbackRoutesWithExtraParams()
    {
        $router = new Router;
        $callbackRoute = $router->get('/', function() {}, array('extra'));

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
    }

    /**
     * @covers  Respect\Rest\Router::__call
     */
    public function testMagicConstructorCanRouteToPreBuiltInstances()
    {
        $router = new Router;
        $myInstance = $this->getMockForAbstractClass('Respect\Rest\Routable');
        $instanceRoute = $router->get('/', $myInstance);

        $this->assertInstanceOf(
            'Respect\Rest\Routes\Instance', 
            $instanceRoute,
            'Returned result from a magic constructor in this case should return a Routes\Instance'
        );
    }

    /**
     * @covers  Respect\Rest\Router::__call
     * @dataProvider provideForStaticRoutableValues
     */
    public function testMagicConstructorCanRouteToStaticValue($staticValue, $reason)
    {
        $router = new Router;
        $staticRoute = $router->get('/', $staticValue);

        $this->assertInstanceOf(
            'Respect\Rest\Routes\StaticValue', 
            $staticRoute,
            $reason
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
     * @covers  Respect\Rest\Router::__call
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
     * @covers  Respect\Rest\Router::__call
     */
    public function testMagicConstructorCanRouteToClasses()
    {
        $router = new Router;
        $className = 'GeneratedClass'.md5(rand());
        eval("class $className implements Respect\Rest\Routable{}");
        $classRoute = $router->get('/', $className);

        $this->assertInstanceOf(
            'Respect\Rest\Routes\ClassName', 
            $classRoute,
            'Returned result from a magic constructor in this case should return a Routes\ClassName'
        );
    }


}