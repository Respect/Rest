<?php
namespace Respect\Rest {

/**
 * @covers Respect\Rest\Router
 * @covers Respect\Rest\Request
 * @covers Respect\Rest\Routable
 * @covers Respect\Rest\Routes\AbstractRoute
 * @covers Respect\Rest\Routes\ClassName
 * @covers Respect\Rest\Routes\Factory
 * @covers Respect\Rest\Routes\Instance
 * @covers Respect\Rest\Routes\StaticValue
 * @covers Respect\Rest\Routines\AbstractAccept
 * @covers Respect\Rest\Routines\AbstractCallbackList
 * @covers Respect\Rest\Routines\AbstractCallbackMediator
 * @covers Respect\Rest\Routines\AbstractRoutine
 * @covers Respect\Rest\Routines\AbstractSyncedRoutine
 * @covers Respect\Rest\Routines\Accept
 * @covers Respect\Rest\Routines\AcceptCharset
 * @covers Respect\Rest\Routines\AcceptEncoding
 * @covers Respect\Rest\Routines\AcceptLanguage
 * @covers Respect\Rest\Routines\AuthBasic
 * @covers Respect\Rest\Routines\By
 * @covers Respect\Rest\Routines\ContentType
 * @covers Respect\Rest\Routines\IgnorableFileExtension
 * @covers Respect\Rest\Routines\ProxyableBy
 * @covers Respect\Rest\Routines\LastModified
 * @covers Respect\Rest\Routines\ParamSynced
 * @covers Respect\Rest\Routines\ProxyableBy
 * @covers Respect\Rest\Routines\ProxyableThrough
 * @covers Respect\Rest\Routines\ProxyableWhen
 * @covers Respect\Rest\Routines\Routinable
 * @covers Respect\Rest\Routines\Through
 * @covers Respect\Rest\Routines\Unique
 * @covers Respect\Rest\Routines\UserAgent
 * @covers Respect\Rest\Routines\When
 */
class OldRouterTest extends \PHPUnit_Framework_TestCase
{

    protected $object;
    protected $result;
    protected $callback;

    public function setUp()
    {
//        $this->markTestSkipped();
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
        $this->object->dispatch('get', '/')->response();
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testNotRoutableControllerByName()
    {
        $this->object->classRoute('ANY', '/', '\stdClass');
        $this->object->dispatch('get', '/')->response();
    }

    /**
     * @dataProvider providerForSingleRoutes
     */
    public function testSingleRoutes($route, $path, $expectedParams)
    {
        $this->object->callbackRoute('get', $route, $this->callback);
        $r = $this->object->dispatch('get', $path);
        if ($r)
            $r->response();
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
            $r->response();
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
            $r->response();
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
                array('alganet',array('home', 'alganet', 'Projects', 'RespectRest'))
            ),
            array(
                '/users/*/mounted-folder/*/**',
                '/users/alganet/mounted-folder/from-network/home/alganet/Projects/RespectRest/',
                array('alganet','from-network',array('home', 'alganet', 'Projects', 'RespectRest'))
            )
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
                '/users' . str_repeat('/abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz', 2500),
                str_split(str_repeat('abcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyzabcdefghijklmnopqrstuvwxyz', 2500), 26 * 3)
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
        $result = $this->object->dispatch('get', '/users/alganet')->response();
        $this->assertEquals(array('alganet', 'get', array()), $result);
    }

    public function testBindControllerParams()
    {
        $this->object->any('/users/*', 'Respect\\Rest\\MyController', array('ok'));
        $result = $this->object->dispatch('get', '/users/alganet')->response();
        $this->assertEquals(array('alganet', 'get', array('ok')), $result);
    }

    public function testBindControllerInstance()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController('ok'));
        $result = $this->object->dispatch('get', '/users/alganet')->response();
        $this->assertEquals(array('alganet', 'get', array('ok')), $result);
    }
    public function testBindControllerFactory()
    {
        $this->object->any('/users/*', 'Respect\\Rest\\MyController', function() {
            return  new MyController('ok');
        });
        $result = $this->object->dispatch('get', '/users/alganet')->response();
        $this->assertEquals(array('alganet', 'get', array('ok')), $result);
    }

    public function testBindControllerParams2()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController('ok', 'foo', 'bar'));
        $result = $this->object->dispatch('get', '/users/alganet')->response();
        $this->assertEquals(array('alganet', 'get', array('ok', 'foo', 'bar')), $result);
    }

    public function testBindControllerSpecial()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController);
        $result = $this->object->dispatch('__construct', '/users/alganet')->response();
        $this->assertEquals(null, $result);
    }

    public function testBindControllerMultiMethods()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController);
        $result = $this->object->dispatch('get', '/users/alganet')->response();
        $this->assertEquals(array('alganet', 'get', array()), $result);

        $result = $this->object->dispatch('post', '/users/alganet')->response();
        $this->assertEquals(array('alganet', 'post', array()), $result);
    }

    public function testProxyBy()
    {
        $result = null;
        $proxy = function() use (&$result) {
                $result = 'ok';
            };
        $this->object->get('/users/*', function() {

            })->by($proxy);
        $this->object->dispatch('get', '/users/alganet')->response();
        $this->assertEquals('ok', $result);
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
        $this->object->always('by', $proxy);
        $this->object->get('/users/*', function() {

            });
        $this->object->dispatch('get', '/users/alganet')->response();
        $this->assertEquals('ok', $result);
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
        $this->object->get('/users/*', function() {

            });
        $this->object->always('by', $proxy);
        $this->object->dispatch('get', '/users/alganet')->response();
        $this->assertEquals('ok', $result);
    }

    public function testProxyThrough()
    {
        $result = null;
        $proxy = function() use (&$result) {
                $result = 'ok';
            };
        $this->object->get('/users/*', function() {

            })->through($proxy);
        $this->object->dispatch('get', '/users/alganet')->response();
        $this->assertEquals('ok', $result);
    }

    public function testProxyThroughOutput()
    {
        $proxy = function() {
                return function($output) {
                        return $output . 'ok';
                    };
            };
        $this->object->get('/users/*', function() {
                return 'ok';
            })->through($proxy);
        $result = $this->object->dispatch('get', '/users/alganet')->response();
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
        $this->object->get('/users/*/*/*', function($foo, $bar, $baz) use(&$result) {
                $result[] = 'main';
            })->by($proxy1)->through($proxy2)->through($proxy3);
        $this->object->dispatch('get', '/users/abc/def/ghi')->response();
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
        $this->object->dispatch('get', '/users/abc/def')->response();
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
        $this->object->get('/users/*/*/*', function($foo, $bar, $baz) use(&$result) {
                $result[] = 'main';
            })->by($proxy1)->through($proxy2)->through($proxy3);
        $this->object->dispatch('get', '/users/abc/def/ghi')->response();
        $this->assertSame(
            array('abc'), $result
        );
    }

    public function notestConditions()
    {
        $result = 'ok';
        $condition = function() {
                return false;
            };
        $this->object->get('/users/*', function() use (&$result) {
                $result = null;
            })->when($condition);
        $this->object->dispatch('get', '/users/alganet');
        $this->assertEquals('ok', $result);
    }

    public function testWildcardOrdering()
    {
        $this->object->any('/posts/*/*', function($year, $month) {
                return 10;
            }
        );
        $this->object->any('/**', function($userName) {
                return 5;
            }
        );
        $this->assertEquals(
            10, $this->object->dispatch('get', '/posts/2010/20')->response()
        );
        $this->assertEquals(
            5, $this->object->dispatch('get', '/anything')->response()
        );
    }

    public function testOrdering()
    {
        $this->object->any('/users/*', function($userName) {
                return 5;
            }
        );
        $this->object->any('/users/*/*', function($year, $month) {
                return 10;
            }
        );
        $this->assertEquals(
            5, $this->object->dispatch('get', '/users/alganet')->response()
        );
        $this->assertEquals(
            10, $this->object->dispatch('get', '/users/2010/20')->response()
        );
    }

    public function testOrderingSpecific()
    {
        $this->object->any('/users/*/*', function($year, $month) {
                return 10;
            }
        );
        $this->object->any('/users/lists/*', function($userName) {
                return 5;
            }
        );
        $this->assertEquals(
            5, $this->object->dispatch('get', '/users/lists/alganet')->response()
        );
        $this->assertEquals(
            10, $this->object->dispatch('get', '/users/foobar/alganet')->response()
        );
    }

    public function testOrderingSpecific2()
    {
        $this->object->any('/', function() {
                return 2;
            }
        );
        $this->object->any('/*', function() {
                return 3;
            }
        );
        $this->object->any('/*/versions', function() {
                return 4;
            }
        );
        $this->object->any('/*/versions/*', function() {
                return 5;
            }
        );
        $this->object->any('/*/*', function() {
                return 6;
            }
        );
        $this->assertEquals(
            2, $this->object->dispatch('get', '/')->response()
        );
        $this->assertEquals(
            3, $this->object->dispatch('get', '/foo')->response()
        );
        $this->assertEquals(
            4, $this->object->dispatch('get', '/foo/versions')->response()
        );
        $this->assertEquals(
            5, $this->object->dispatch('get', '/foo/versions/1.0')->response()
        );
        $this->assertEquals(
            6, $this->object->dispatch('get', '/foo/bar')->response()
        );
    }

    public function testExperimentalShell()
    {
        $router = new Router;
        $router->install('/**', function() {
                return 'Installed ' . implode(', ', func_get_arg(0));
            }
        );
        $commandLine = 'install apache php mysql';
        $commandArgs = explode(' ', $commandLine);
        $output = $router->dispatch(
                array_shift($commandArgs), '/' . implode('/', $commandArgs)
            )->response();
        $this->assertEquals('Installed apache, php, mysql', $output);
    }

    public function testAccept()
    {
        $_SERVER['REQUEST_URI'] = '/users/alganet';
        $request = new Request('get', '/users/alganet');
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->object->get('/users/*', function() {
                return range(0, 10);
            })->accept(array('application/json' => 'json_encode'));
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals(json_encode(range(0, 10)), $r);
    }


    public function testAcceptCharset()
    {
        $request = new Request('get', '/users/alganet');
        $_SERVER['HTTP_ACCEPT_CHARSET'] = 'utf-8';
        $this->object->get('/users/*', function() {
                return 'açaí';
            })->acceptCharset(array('utf-8' => 'utf8_decode'));
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals(utf8_decode('açaí'), $r);
    }


    public function testAcceptEncoding()
    {
        $request = new Request('get', '/users/alganet');
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'myenc';
        $this->object->get('/users/*', function() {
                return 'foobar';
            })->acceptEncoding(array('myenc' => 'strrev'));
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals(strrev('foobar'), $r);
    }

    public function testAcceptUrl()
    {
        $request = new Request('get', '/users/alganet.json');
        $this->object->get('/users/*', function($screenName) {
                return range(0, 10);
            })->accept(array('.json' => 'json_encode'));
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals(json_encode(range(0, 10)), $r);
    }
    public function testAcceptUrlNoParameters()
    {
        $request = new Request('get', '/users.json');
        $this->object->get('/users', function() {
                return range(0, 10);
            })->accept(array('.json' => 'json_encode'));
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals(json_encode(range(0, 10)), $r);
    }
    public function testFileExtension()
    {
        $request = new Request('get', '/users.json/10.20');
        $this->object->get('/users.json/*', function($param) {
                list($min, $max) = explode('.', $param);
                return range($min, $max);
            });
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals((range(10, 20)), $r);
    }

    public function notestAcceptGeneric()
    {
        $request = new Request('get', '/users/alganet');
        $_SERVER['HTTP_ACCEPT'] = 'application/*';
        $this->object->get('/users/*', function() {
                return range(0, 10);
            })->accept(array('application/json' => 'json_encode'));
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testAcceptGeneric2()
    {
        $request = new Request('get', '/users/alganet');
        $_SERVER['HTTP_ACCEPT'] = '*/*';
        $this->object->get('/users/*', function() {
                return range(0, 10);
            })->accept(array('application/json' => 'json_encode'));
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals(json_encode(range(0, 10)), $r);
    }

    public function notestAcceptGeneric3()
    {
        $request = new Request('get', '/users/alganet');
        $_SERVER['HTTP_ACCEPT'] = 'text/*';
        $this->object->get('/users/*', function() {
                return range(0, 10);
            })->accept(array('application/json' => 'json_encode'));
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals(null, $r);
    }

    public function testAcceptLanguage()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $request = new Request('get', '/users/alganet');
        $this->object->get('/users/*', function() {

            })->acceptLanguage(array(
            'en-US' => function() {
                return 'Hi there';
            },
            'pt-BR' => function() {
                return 'Olá!';
            }));
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals('Hi there', $r);
    }

    public function testAcceptLanguage2()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt';
        $request = new Request('get', '/users/alganet');
        $this->object->get('/users/*', function() {

            })->acceptLanguage(array(
            'en-US' => function() {
                return 'Hi there';
            },
            'pt-BR' => function() {
                return 'Olá!';
            }));
        $r = $this->object->dispatchRequest($request)->response();
        $this->assertEquals('Olá!', $r);
    }

    public function testAcceptOrder()
    {
        $requestBoth = new Request('get', '/users/alganet');
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt,en';
        $this->object->get('/users/*', function() {

            })->acceptLanguage(array(
            'en' => function() {
                return 'Hi there';
            },
            'pt' => function() {
                return 'Olá!';
            }));
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertEquals('Olá!', $r);
    }
    public function testUniqueRoutine()
    {
        $requestBoth = new Request('get', '/users/alganet');
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt,en';
        $neverRun = false;
        $this->object->get('/users/*', function() {

            })->acceptLanguage(array(
            'en' => function() use (&$neverRun){
                $neverRun = true;
            },
            'pt' => function() use (&$neverRun){
                $neverRun = true;
            }))->acceptLanguage(array(
            'en' => function() {
                return 'dsfdfsdfsdf';
            },
            'pt' => function() {
                return 'sdfsdfsdfdf!';
            }));
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertFalse($neverRun);
    }

    public function testAcceptMulti()
    {
        $requestBoth = new Request('get', '/users/alganet');
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt,en';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->object->get('/users/*', function($data) {
                return '034930984';
            })->acceptLanguage(array(
            'en' => function() {
                return 'Hi there';
            },
            'pt' => function() {
                return 'Olá!';
            }))->accept(array(
            'application/json' => 'json_encode'
        ));
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertEquals('"Ol\u00e1!"', $r);
    }

    public function testAcceptOrderX()
    {
        $requestBoth = new Request('get', '/users/alganet');
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'x-klingon,en';
        $this->object->get('/users/*', function() {

            })->acceptLanguage(array(
            'en' => function() {
                return 'Hi there';
            },
            'klingon-tr' => function() {
                return 'nuqneH';
            }));
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertEquals('nuqneH', $r);
    }

    public function testAcceptOrderQuality()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt;q=0.7,en';
        $requestBoth = new Request('get', '/users/alganet');
        $this->object->get('/users/*', function() {

            })->acceptLanguage(array(
            'en-US' => function() {
                return 'Hi there';
            },
            'pt-BR' => function() {
                return 'Olá!';
            }));
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertEquals('Hi there', $r);
    }

    public function testLastModifiedSince()
    {
        global $header;
        $header = array();
        $_SERVER['IF_MODIFIED_SINCE'] = '2011-11-11 11:11:11';
        $requestBoth = new Request('get', '/users/alganet');
        $this->object->get('/users/*', function() {
                return 'hi!';
            })->lastModified(
            function() {
                return new \DateTime('2011-11-11 11:11:12');
            });
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertEquals('hi!', $r);
        $this->assertContains('Last-Modified: Fri, 11 Nov 2011 11:11:12 +0000', $header);
        $this->assertNotContains('HTTP/1.1 304 Not Modified', $header);
    }

    public function testLastModifiedSince2()
    {
        global $header;
        $header = array();
        $_SERVER['IF_MODIFIED_SINCE'] = '2011-11-11 11:11:11';
        $requestBoth = new Request('get', '/users/alganet');
        $this->object->get('/users/*', function() {
                return 'hi!';
            })->lastModified(
            function() {
                return new \DateTime('2011-11-11 11:11:10');
            });
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertEquals('', $r);
        $this->assertContains('Last-Modified: Fri, 11 Nov 2011 11:11:10 +0000', $header);
        $this->assertContains('HTTP/1.1 304 Not Modified', $header);
    }

    public function testLastModifiedSince3()
    {
        global $header;
        $header = array();
        $_SERVER['IF_MODIFIED_SINCE'] = '2011-11-11 11:11:11';
        $requestBoth = new Request('get', '/users/alganet');
        $this->object->get('/users/*', function() {
                return 'hi!';
            })->lastModified(
            function() {
                return new \DateTime('2011-11-11 11:11:11');
            });
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertEquals('', $r);
        $this->assertContains('Last-Modified: Fri, 11 Nov 2011 11:11:11 +0000', $header);
        $this->assertContains('HTTP/1.1 304 Not Modified', $header);
    }

    public function testContenType()
    {
        $_SERVER['CONTENT_TYPE'] = 'text/xml';
        $requestBoth = new Request('get', '/users/alganet');
        $result = null;
        $this->object->get('/users/*', function() {

            })->contentType(array(
            'text/json' => function() {

            },
            'text/xml' => function() use (&$result) {
                $result = 'ok';
            }));
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertEquals('ok', $result);
    }

    public function testVirtualHost()
    {
        $router = new Router('/myvh');
        $ok = false;
        $router->get('/alganet', function() use (&$ok) {
                $ok = true;
            }
        );
        $router->dispatch('get', '/myvh/alganet')->response();
        $this->assertTrue($ok);
    }

    public function testVirtualHostEmpty()
    {
        $router = new Router('/myvh');
        $ok = false;
        $router->get('/', function() use (&$ok) {
                $ok = true;
            }
        );
        $router->dispatch('get', '/myvh')->response();
        $this->assertTrue($ok);
    }

    public function testVirtualHostIndex()
    {
        $router = new Router('/myvh/index.php');
        $ok = false;
        $router->get('/', function() use (&$ok) {
                $ok = true;
            }
        );
        $router->dispatch('get', '/myvh/index.php')->response();
        $this->assertTrue($ok);
    }

    public function testCreateUri()
    {
        $r = new Router;
        $ro = $r->any('/users/*/test/*', function() {

            });
        $this->assertEquals(
            '/users/alganet/test/php', $ro->createUri("alganet", "php")
        );
        $r->isAutoDispatched = false;
    }
    public function testForward()
    {
        $r = new Router;
        $ro1 = $r->any('/users/*', function($user) {
            return $user;
        });
        $ro2 = $r->any('/*', function($user) use ($ro1) {
            return $ro1;
        });
        $response = $r->dispatch('get', '/alganet')->response();
        $this->assertEquals('alganet', $response);
    }

    /**
     * @group issues
     * @ticket 37
     **/
    public function test_optional_parameter_in_class_routes()
    {
        $r = new Router();
        $r->any('/optional/*', 'Respect\Rest\MyOptionalParamRoute');
        $response = $r->dispatch('get', '/optional')->response();
        $this->assertEquals('John Doe', (string) $response);
    }
}
if (!function_exists(__NAMESPACE__.'\\header')) {
    function header($string, $replace=true, $http_response_code=200)
    {
        global $header;
        if (!$replace && isset($header))
            return;

        $header[$string] = $string;
        $h = $string;
        $s = debug_backtrace(true);
        $rt = function($a) {return isset($a['object'])
            && $a['object'] instanceof RouterTest;};
        if (array_filter($s, $rt) && 0 === strpos($h, 'HTTP/1.1 ')) {
            RouterTest::$status = substr($h, 9);
        }
        return @\header($h);
    }
}

class MyOptionalParamRoute implements Routable
{
    public function get($user=null)
    {
        return $user ?: 'John Doe';
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
}

namespace Respect\Rest\Routines {
    if (!function_exists(__NAMESPACE__.'\\header')) {
        function header($string, $replace=true, $http_response_code=200)
        {
            global $header;
            if (!$replace && isset($header))
                return;

            $header[$string] = $string;
        }
    }
}

namespace {
    $header=array();
}
