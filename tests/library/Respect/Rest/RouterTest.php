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
     * @expectedException InvalidArgumentException
     */
    public function testInsufficientParams()
    {
        $this->object->invalid();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testNotRoutableController()
    {
        $this->object->instanceRoute('ANY', '/', new \stdClass);
        $this->object->dispatch('get', '/')->run();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testNotRoutableControllerByName()
    {
        $this->object->classRoute('ANY', '/', '\stdClass');
        $this->object->dispatch('get', '/')->run();
    }

    /**
     * @dataProvider providerForSingleRoutes
     */
    public function testSingleRoutes($route, $path, $expectedParams)
    {
        $this->object->callbackRoute('get', $route, $this->callback);
        $r = $this->object->dispatch('get', $path);
        if ($r)
            $r->run();
        $this->assertEquals($expectedParams, $this->result);
    }

    /**
     * @dataProvider providerForLargeParams
     */
    public function testLargeParams($route, $path, $expectedParams)
    {

        $this->object->callbackRoute('get', $route, $this->callback);
        $r = $this->object->dispatch('get', $path);
        if ($r)
            $r->run();
        $this->assertEquals($expectedParams, $this->result);
    }

    /**
     * @dataProvider providerForSpecialChars
     */
    public function testSpecialChars($route, $path, $expectedParams)
    {

        $this->object->callbackRoute('get', $route, $this->callback);
        $r = $this->object->dispatch('get', $path);
        if ($r)
            $r->run();
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
                '/users/*/lists/*',
                '/users/1/lists/2/65465',
                null //cant match
            ),
            array(
                '/users/*/lists/*/*',
                '/users/1/lists/2/3',
                array(1, 2, 3)
            ),
            array(
                '/posts/*/*/*',
                '/posts/2010/10/10',
                array(2010, 10, 10)
            ),
            array(
                '/posts/*/*/*',
                '/posts/2010/10',
                array(2010, 10)
            ),
            array(
                '/posts/*/*/*',
                '/posts/2010',
                array(2010)
            ),
            array(
                '/posts/*/*/*',
                '/posts/2010/10///',
                array(2010, 10)
            ),
            array(
                '/posts/*/*/*',
                '/posts/2010/////',
                array(2010)
            ),
            array(
                '/posts/*/*/*',
                '/posts/2010//10///',
                null
            ),
            array(
                '/posts/*/*/*',
                '/posts/2010/0/',
                array(2010, 0)
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
        return array(
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
                '/users' . str_repeat('/*', 2500), //2500 short parameters
                '/users' . str_repeat('/xy', 2500),
                str_split(str_repeat('xy', 2500), 2)
            ),
            array(
                '/users' . str_repeat('/*', 2500), //2500 large parameters
                '/users' . str_repeat('/abcdefghijklmnopqrstuvwxyz', 2500),
                str_split(str_repeat('abcdefghijklmnopqrstuvwxyz', 2500), 26)
            ),
            array(
                '/users' . str_repeat('/*', 2500), //2500 very large parameters
                '/users' . str_repeat('/abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz',
                    2500),
                str_split(str_repeat('abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz',
                        2500), 26 * 3)
            ),
        );
    }

    public function providerForSpecialChars()
    {
        return array(
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
            array(
                '/shinny*/*',
                '/shinny*/2',
                array(2)
            ),
        );
    }

    public function testBindControllerNoParams()
    {
        $this->object->any('/users/*', new MyController);
        $result = $this->object->dispatch('get', '/users/alganet')->run();
        $this->assertEquals(array('alganet', 'get', array()), $result);
    }

    public function testBindControllerParams()
    {
        $this->object->any('/users/*', 'Respect\\Rest\\MyController', 'ok');
        $result = $this->object->dispatch('get', '/users/alganet')->run();
        $this->assertEquals(array('alganet', 'get', array('ok')), $result);
    }

    public function testBindControllerInstance()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController('ok'));
        $result = $this->object->dispatch('get', '/users/alganet')->run();
        $this->assertEquals(array('alganet', 'get', array('ok')), $result);
    }

    public function testBindControllerParams2()
    {
        $this->object->instanceRoute('ANY', '/users/*',
            new MyController('ok', 'foo', 'bar'));
        $result = $this->object->dispatch('get', '/users/alganet')->run();
        $this->assertEquals(array('alganet', 'get', array('ok', 'foo', 'bar')),
            $result);
    }

    public function testBindControllerSpecial()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController);
        $result = $this->object->dispatch('__construct', '/users/alganet');
        $this->assertEquals(null, $result);
    }

    public function testBindControllerMultiMethods()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController);
        $result = $this->object->dispatch('get', '/users/alganet')->run();
        $this->assertEquals(array('alganet', 'get', array()), $result);

        $result = $this->object->dispatch('post', '/users/alganet')->run();
        $this->assertEquals(array('alganet', 'post', array()), $result);
    }

    public function testProxyBy()
    {
        $result = null;
        $proxy = function() use (&$result) {
                $result = 'ok';
            };
        $this->object->get('/users/*',
            function() {
                
            })->by($proxy);
        $this->object->dispatch('get', '/users/alganet')->run();
        $this->assertEquals('ok', $result);
    }

    public function testProxyThen()
    {
        $result = null;
        $proxy = function() use (&$result) {
                $result = 'ok';
            };
        $this->object->get('/users/*',
            function() {
                
            })->then($proxy);
        $this->object->dispatch('get', '/users/alganet')->run();
        $this->assertEquals('ok', $result);
    }

    public function testProxyThenOutput()
    {
        $proxy = function() {
                return function($output) {
                    return $output . 'ok';
                };
            };
        $this->object->get('/users/*',
            function() {
                return 'ok';
            })->then($proxy);
        $result = $this->object->dispatch('get', '/users/alganet')->run();
        $this->assertEquals('okok', $result);
    }

    public function testMultipleProxies()
    {
        $result = array();
        $proxy1 = function($foo) use (&$result) {
                $result[] = $foo;
            };
        $proxy2 = function($bar) use (&$result) {
                $result[] = $bar;
            };
        $proxy3 = function($baz) use (&$result) {
                $result[] = $baz;
            };
        $this->object->get('/users/*/*/*',
            function($foo, $bar, $baz) use(&$result) {
                $result[] = 'main';
            })->by($proxy1)->then($proxy2, $proxy3);
        $this->object->dispatch('get', '/users/abc/def/ghi')->run();
        $this->assertSame(
            array('abc', 'main', 'def', 'ghi'), $result
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
        $this->object->get('/users/*/*', $callback)->by($proxy1);
        $this->object->dispatch('get', '/users/abc/def')->run();
        $this->assertEquals(array('def', null), $resultProxy);
        $this->assertEquals(array('abc', 'def'), $resultCallback);
    }

    public function testProxyReturnFalse()
    {
        $result = array();
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
        $this->object->get('/users/*/*/*',
            function($foo, $bar, $baz) use(&$result) {
                $result[] = 'main';
            })->by($proxy1)->then($proxy2, $proxy3);
        $this->object->dispatch('get', '/users/abc/def/ghi')->run();
        $this->assertSame(
            array('abc'), $result
        );
    }

    public function testConditions()
    {
        $result = 'ok';
        $condition = function() {
                return false;
            };
        $this->object->get('/users/*',
            function() use (&$result) {
                $result = null;
            })->when($condition);
        $this->object->dispatch('get', '/users/alganet');
        $this->assertEquals('ok', $result);
    }

    public function testWildcardOrdering()
    {
        $this->object->any('/posts/*/*',
            function($year, $month) {
                return 10;
            }
        );
        $this->object->any('/**',
            function($userName) {
                return 5;
            }
        );
        $this->assertEquals(
            10, $this->object->dispatch('get', '/posts/2010/20')->run()
        );
        $this->assertEquals(
            5, $this->object->dispatch('get', '/anything')->run()
        );
    }

    public function testOrdering()
    {
        $this->object->any('/users/*',
            function($userName) {
                return 5;
            }
        );
        $this->object->any('/users/*/*',
            function($year, $month) {
                return 10;
            }
        );
        $this->assertEquals(
            5, $this->object->dispatch('get', '/users/alganet')->run()
        );
        $this->assertEquals(
            10, $this->object->dispatch('get', '/users/2010/20')->run()
        );
    }

    public function testOrderingSpecific()
    {
        $this->object->any('/users/*/*',
            function($year, $month) {
                return 10;
            }
        );
        $this->object->any('/users/lists/*',
            function($userName) {
                return 5;
            }
        );
        $this->assertEquals(
            5, $this->object->dispatch('get', '/users/lists/alganet')->run()
        );
        $this->assertEquals(
            10, $this->object->dispatch('get', '/users/foobar/alganet')->run()
        );
    }

    public function testOrderingSpecific2()
    {
        $this->object->any('/',
            function() {
                return 2;
            }
        );
        $this->object->any('/*',
            function() {
                return 3;
            }
        );
        $this->object->any('/*/versions',
            function() {
                return 4;
            }
        );
        $this->object->any('/*/versions/*',
            function() {
                return 5;
            }
        );
        $this->object->any('/*/*',
            function() {
                return 6;
            }
        );
        $this->assertEquals(
            2, $this->object->dispatch('get', '/')->run()
        );
        $this->assertEquals(
            3, $this->object->dispatch('get', '/foo')->run()
        );
        $this->assertEquals(
            4, $this->object->dispatch('get', '/foo/versions')->run()
        );
        $this->assertEquals(
            5, $this->object->dispatch('get', '/foo/versions/1.0')->run()
        );
        $this->assertEquals(
            6, $this->object->dispatch('get', '/foo/bar')->run()
        );
    }

}

//couldn't mock this 'cause its read by reflection =/
class MyController implements Routable
{

    protected $params = array();

    public function __construct()
    {
        $this->params = func_get_args();
        return 'whoops';
    }

    public function get($user)
    {
        return array($user, 'get', $this->params);
    }

    public function post($user)
    {
        return array($user, 'post', $this->params);
    }

}