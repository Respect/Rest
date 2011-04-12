<?php

namespace Respect\Rest;

class RouterTest extends \PHPUnit_Framework_TestCase
{

    protected $object;
    protected $result;
    protected $callback;

    public function setUp()
    {
        $this->object = new Router;
        $this->result = null;
        $result = &$this->result;
        $this->callback = function() use(&$result) {
                $result = func_get_args();
            };
    }

    /**
     * @dataProvider providerForSingleRoutes
     */
    public function testSingleRoutes($route, $path, $expectedParams)
    {
        $this->object->addRoute('get', $route, $this->callback);
        $this->object->dispatch('get', $path);
        $this->assertEquals($expectedParams, $this->result);
    }

    /**
     * @dataProvider providerForLargeParams
     */
    public function testLargeParams($route, $path, $expectedParams)
    {

        $this->object->addRoute('get', $route, $this->callback);
        $this->object->dispatch('get', $path);
        $this->assertEquals($expectedParams, $this->result);
    }

    /**
     * @dataProvider providerForSpecialChars
     */
    public function testSpecialChars($route, $path, $expectedParams)
    {

        $this->object->addRoute('get', $route, $this->callback);
        $this->object->dispatch('get', $path);
        $this->assertEquals($expectedParams, $this->result);
    }

    public function providerForSingleRoutes()
    {
        return array(
            array(
                '/',
                '/',
                array()
            ),
            array(
                '/users',
                '/users',
                array()
            ),
            array(
                '/users/',
                '/users',
                array()
            ),
            array(
                '/users',
                '/users/',
                array()
            ),
            array(
                '/users/*',
                '/users/1',
                array(1)
            ),
            array(
                '/users/*/*',
                '/users/1/2',
                array(1, 2)
            ),
            array(
                '/users/*/lists',
                '/users/1/lists',
                array(1)
            ),
            array(
                '/users/*/lists/*',
                '/users/1/lists/2',
                array(1, 2)
            ),
            array(
                '/users/*/lists/*/*',
                '/users/1/lists/2/3',
                array(1, 2, 3)
            ),
            array(
                '/users/*/*/lists/*/*',
                '/users/1/1B/lists/2/3',
                array(1, '1B', 2, 3)
            ),
            array(
                '/users/*/mounted-folder/**',
                '/users/alganet/mounted-folder/home/alganet/Projects/RespectRest/',
                array('alganet', '/home/alganet/Projects/RespectRest')
            ),
        );
    }

    public function providerForLargeParams()
    {
        return
        array(
            array(
                '/users/*/*/*/*/*/*/*',
                '/users/1',
                array(1)
            ),
            array(
                '/users/*/*/*/*/*/*/*',
                '/users/a/a/a/a/a/a/a',
                array('a', 'a', 'a', 'a', 'a', 'a', 'a')
            ),
            array(
                '/users' . str_repeat('/*', 300), //300 short parameters
                '/users' . str_repeat('/xy', 300),
                str_split(str_repeat('xy', 300), 2)
            ),
            array(
                '/users' . str_repeat('/*', 60), //60 large parameters
                '/users' . str_repeat('/abcdefghijklmnopqrstuvwxyz', 60),
                str_split(str_repeat('abcdefghijklmnopqrstuvwxyz', 60), 26)
            ),
        );
    }

    public function providerForSpecialChars()
    {
        return
        array(
            array(
                '/My Documents/*',
                '/My Documents/1',
                array(1)
            ),
            array(
                '/My%20Documents/*', //trival
                '/My%20Documents/1',
                array(1)
            ),
            array(
                '/(.*)/*/[a-z]/*', //preg_quote ftw, but you're a SOB if you
                '/(.*)/1/[a-z]/2', //create a route with those special chars
                array(1, 2)
            ),
        );
    }

}

