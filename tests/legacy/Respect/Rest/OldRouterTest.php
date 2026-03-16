<?php
namespace Respect\Rest {

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\DataProvider;

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
class OldRouterTest extends \PHPUnit\Framework\TestCase
{

    protected $object;
    protected $result;
    protected $callback;

    public function setUp(): void
    {
//        $this->markTestSkipped();
        $this->object = new Router(new Psr17Factory());
        $this->result = null;
        $result = &$this->result;
        $this->callback = function() use(&$result) {
                $result = func_get_args();
            };
    }

    /**
     */
    public function testInsufficientParams()
    {
        $this->expectException('InvalidArgumentException');
        $this->object->invalid();
    }

    /**
     */
    public function testNotRoutableController()
    {
        $this->expectException('InvalidArgumentException');
        $this->object->instanceRoute('ANY', '/', new \stdClass);
        (string) $this->object->dispatch(new ServerRequest('get', '/'))->response()->getBody();
    }

    /**
     */
    public function testNotRoutableControllerByName()
    {
        $this->expectException('InvalidArgumentException');
        $this->object->classRoute('ANY', '/', '\\stdClass');
        (string) $this->object->dispatch(new ServerRequest('get', '/'))->response()->getBody();
    }

    #[DataProvider('providerForSingleRoutes')]
    public function testSingleRoutes($route, $path, $expectedParams)
    {
        $this->object->callbackRoute('get', $route, $this->callback);
        $r = $this->object->dispatch(new ServerRequest('get', $path));
        if ($r)
            $r->response();
        $this->assertEquals($expectedParams, $this->result);
    }

    #[DataProvider('providerForLargeParams')]
    public function testLargeParams($route, $path, $expectedParams)
    {

        $this->object->callbackRoute('get', $route, $this->callback);
        $r = $this->object->dispatch(new ServerRequest('get', $path));
        if ($r)
            $r->response();
        $this->assertEquals($expectedParams, $this->result);
    }

    #[DataProvider('providerForSpecialChars')]
    public function testSpecialChars($route, $path, $expectedParams)
    {

        $this->object->callbackRoute('get', $route, $this->callback);
        $r = $this->object->dispatch(new ServerRequest('get', $path));
        if ($r)
            $r->response();
        $this->assertEquals($expectedParams, $this->result);
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
                '/users' . str_repeat('/*', 2500), //2500 short parameters
                '/users' . str_repeat('/xy', 2500),
                str_split(str_repeat('xy', 2500), 2)
            ],
            [
                '/users' . str_repeat('/*', 2500), //2500 large parameters
                '/users' . str_repeat('/abcdefghijklmnopqrstuvwxyz', 2500),
                str_split(str_repeat('abcdefghijklmnopqrstuvwxyz', 2500), 26)
            ],
            [
                '/users' . str_repeat('/*', 2500), //2500 very large parameters
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
                '/My Documents/*', //PSR-7 decodes %20 to space
                '/My%20Documents/1',
                [1]
            ],
            [
                '/(.*)/*/[a-z]/*', //preg_quote ftw, but you're a SOB if you
                '/(.*)/1/[a-z]/2', //create a route with those special chars
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
        $this->object->any('/users/*', new MyController);
        $result = (string) $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        $this->assertEquals(json_encode(['alganet', 'get', []]), $result);
    }

    public function testBindControllerParams()
    {
        $this->object->any('/users/*', 'Respect\\Rest\\MyController', ['ok']);
        $result = (string) $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        $this->assertEquals(json_encode(['alganet', 'get', ['ok']]), $result);
    }

    public function testBindControllerInstance()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController('ok'));
        $result = (string) $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        $this->assertEquals(json_encode(['alganet', 'get', ['ok']]), $result);
    }
    public function testBindControllerFactory()
    {
        $this->object->any('/users/*', 'Respect\\Rest\\MyController', function() {
            return  new MyController('ok');
        });
        $result = (string) $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        $this->assertEquals(json_encode(['alganet', 'get', ['ok']]), $result);
    }

    public function testBindControllerParams2()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController('ok', 'foo', 'bar'));
        $result = (string) $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        $this->assertEquals(json_encode(['alganet', 'get', ['ok', 'foo', 'bar']]), $result);
    }

    public function testBindControllerSpecial()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController);
        $result = $this->object->dispatch(new ServerRequest('__construct', '/users/alganet'))->response();
        $this->assertEquals(null, $result);
    }

    public function testBindControllerMultiMethods()
    {
        $this->object->instanceRoute('ANY', '/users/*', new MyController);
        $result = (string) $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        $this->assertEquals(json_encode(['alganet', 'get', []]), $result);

        $result = (string) $this->object->dispatch(new ServerRequest('post', '/users/alganet'))->response()->getBody();
        $this->assertEquals(json_encode(['alganet', 'post', []]), $result);
    }

    public function testProxyBy()
    {
        $result = null;
        $proxy = function() use (&$result) {
                $result = 'ok';
            };
        $this->object->get('/users/*', function() {

            })->by($proxy);
        $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response();
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
        $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response();
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
        $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response();
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
        $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response();
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
        $result = (string) $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody();
        $this->assertEquals('okok', $result);
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
        $this->object->get('/users/*/*/*', function($foo, $bar, $baz) use(&$result) {
                $result[] = 'main';
            })->by($proxy1)->through($proxy2)->through($proxy3);
        $this->object->dispatch(new ServerRequest('get', '/users/abc/def/ghi'))->response();
        $this->assertSame(
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
        $this->object->get('/users/*/*', $callback)->by($proxy1);
        $this->object->dispatch(new ServerRequest('get', '/users/abc/def'))->response();
        $this->assertEquals(['def', null], $resultProxy);
        $this->assertEquals(['abc', 'def'], $resultCallback);
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
        $this->object->get('/users/*/*/*', function($foo, $bar, $baz) use(&$result) {
                $result[] = 'main';
            })->by($proxy1)->through($proxy2)->through($proxy3);
        $this->object->dispatch(new ServerRequest('get', '/users/abc/def/ghi'))->response();
        $this->assertSame(
            ['abc'], $result
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
        $this->object->dispatch(new ServerRequest('get', '/users/alganet'));
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
            '10', (string) $this->object->dispatch(new ServerRequest('get', '/posts/2010/20'))->response()->getBody()
        );
        $this->assertEquals(
            '5', (string) $this->object->dispatch(new ServerRequest('get', '/anything'))->response()->getBody()
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
            '5', (string) $this->object->dispatch(new ServerRequest('get', '/users/alganet'))->response()->getBody()
        );
        $this->assertEquals(
            '10', (string) $this->object->dispatch(new ServerRequest('get', '/users/2010/20'))->response()->getBody()
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
            '5', (string) $this->object->dispatch(new ServerRequest('get', '/users/lists/alganet'))->response()->getBody()
        );
        $this->assertEquals(
            '10', (string) $this->object->dispatch(new ServerRequest('get', '/users/foobar/alganet'))->response()->getBody()
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
            '2', (string) $this->object->dispatch(new ServerRequest('get', '/'))->response()->getBody()
        );
        $this->assertEquals(
            '3', (string) $this->object->dispatch(new ServerRequest('get', '/foo'))->response()->getBody()
        );
        $this->assertEquals(
            '4', (string) $this->object->dispatch(new ServerRequest('get', '/foo/versions'))->response()->getBody()
        );
        $this->assertEquals(
            '5', (string) $this->object->dispatch(new ServerRequest('get', '/foo/versions/1.0'))->response()->getBody()
        );
        $this->assertEquals(
            '6', (string) $this->object->dispatch(new ServerRequest('get', '/foo/bar'))->response()->getBody()
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
        $this->assertEquals('Installed apache, php, mysql', $output);
    }

    public function testAccept()
    {
        $_SERVER['REQUEST_URI'] = '/users/alganet';
        $request = new Request(new ServerRequest('get', '/users/alganet'));
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->object->get('/users/*', function() {
                return range(0, 10);
            })->accept(['application/json' => 'json_encode']);
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals(json_encode(range(0, 10)), $r);
    }


    public function testAcceptCharset()
    {
        $request = new Request(new ServerRequest('get', '/users/alganet'));
        $_SERVER['HTTP_ACCEPT_CHARSET'] = 'utf-8';
        $this->object->get('/users/*', function() {
                return 'açaí';
            })->acceptCharset(['utf-8' => fn ($data) => mb_convert_encoding($data, 'ISO-8859-1', 'UTF-8')]);
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals(mb_convert_encoding('açaí', 'ISO-8859-1', 'UTF-8'), $r);
    }


    public function testAcceptEncoding()
    {
        $request = new Request(new ServerRequest('get', '/users/alganet'));
        $_SERVER['HTTP_ACCEPT_ENCODING'] = 'myenc';
        $this->object->get('/users/*', function() {
                return 'foobar';
            })->acceptEncoding(['myenc' => 'strrev']);
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals(strrev('foobar'), $r);
    }

    public function testAcceptUrl()
    {
        $request = new Request(new ServerRequest('get', '/users/alganet.json'));
        $this->object->get('/users/*', function($screenName) {
                return range(0, 10);
            })->accept(['.json' => 'json_encode']);
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals(json_encode(range(0, 10)), $r);
    }
    public function testAcceptUrlNoParameters()
    {
        $request = new Request(new ServerRequest('get', '/users.json'));
        $this->object->get('/users', function() {
                return range(0, 10);
            })->accept(['.json' => 'json_encode']);
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals(json_encode(range(0, 10)), $r);
    }
    public function testFileExtension()
    {
        $request = new Request(new ServerRequest('get', '/users.json/10.20'));
        $this->object->get('/users.json/*', function($param) {
                [$min, $max] = explode('.', $param);
                return range($min, $max);
            });
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals(json_encode(range(10, 20)), $r);
    }

    public function notestAcceptGeneric()
    {
        $request = new Request(new ServerRequest('get', '/users/alganet'));
        $_SERVER['HTTP_ACCEPT'] = 'application/*';
        $this->object->get('/users/*', function() {
                return range(0, 10);
            })->accept(['application/json' => 'json_encode']);
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals(json_encode(range(0, 10)), $r);
    }

    public function testAcceptGeneric2()
    {
        $request = new Request(new ServerRequest('get', '/users/alganet'));
        $_SERVER['HTTP_ACCEPT'] = '*/*';
        $this->object->get('/users/*', function() {
                return range(0, 10);
            })->accept(['application/json' => 'json_encode']);
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals(json_encode(range(0, 10)), $r);
    }

    public function notestAcceptGeneric3()
    {
        $request = new Request(new ServerRequest('get', '/users/alganet'));
        $_SERVER['HTTP_ACCEPT'] = 'text/*';
        $this->object->get('/users/*', function() {
                return range(0, 10);
            })->accept(['application/json' => 'json_encode']);
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals('', $r);
    }

    public function testAcceptLanguage()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        $request = new Request(new ServerRequest('get', '/users/alganet'));
        $this->object->get('/users/*', function() {

            })->acceptLanguage([
            'en-US' => function() {
                return 'Hi there';
            },
            'pt-BR' => function() {
                return 'Olá!';
            }]);
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals('Hi there', $r);
    }

    public function testAcceptLanguage2()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt';
        $request = new Request(new ServerRequest('get', '/users/alganet'));
        $this->object->get('/users/*', function() {

            })->acceptLanguage([
            'en-US' => function() {
                return 'Hi there';
            },
            'pt-BR' => function() {
                return 'Olá!';
            }]);
        $r = (string) $this->object->dispatchRequest($request)->response()->getBody();
        $this->assertEquals('Olá!', $r);
    }

    public function testAcceptOrder()
    {
        $requestBoth = new Request(new ServerRequest('get', '/users/alganet'));
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt,en';
        $this->object->get('/users/*', function() {

            })->acceptLanguage([
            'en' => function() {
                return 'Hi there';
            },
            'pt' => function() {
                return 'Olá!';
            }]);
        $r = (string) $this->object->dispatchRequest($requestBoth)->response()->getBody();
        $this->assertEquals('Olá!', $r);
    }
    public function testUniqueRoutine()
    {
        $requestBoth = new Request(new ServerRequest('get', '/users/alganet'));
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt,en';
        $neverRun = false;
        $this->object->get('/users/*', function() {

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
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertFalse($neverRun);
    }

    public function testAcceptMulti()
    {
        $requestBoth = new Request(new ServerRequest('get', '/users/alganet'));
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt,en';
        $_SERVER['HTTP_ACCEPT'] = 'application/json';
        $this->object->get('/users/*', function($data) {
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
        $r = (string) $this->object->dispatchRequest($requestBoth)->response()->getBody();
        $this->assertEquals('"Ol\u00e1!"', $r);
    }

    public function testAcceptOrderX()
    {
        $requestBoth = new Request(new ServerRequest('get', '/users/alganet'));
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'x-klingon,en';
        $this->object->get('/users/*', function() {

            })->acceptLanguage([
            'en' => function() {
                return 'Hi there';
            },
            'klingon-tr' => function() {
                return 'nuqneH';
            }]);
        $r = (string) $this->object->dispatchRequest($requestBoth)->response()->getBody();
        $this->assertEquals('nuqneH', $r);
    }

    public function testAcceptOrderQuality()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt;q=0.7,en';
        $requestBoth = new Request(new ServerRequest('get', '/users/alganet'));
        $this->object->get('/users/*', function() {

            })->acceptLanguage([
            'en-US' => function() {
                return 'Hi there';
            },
            'pt-BR' => function() {
                return 'Olá!';
            }]);
        $r = (string) $this->object->dispatchRequest($requestBoth)->response()->getBody();
        $this->assertEquals('Hi there', $r);
    }

    public function testLastModifiedSince()
    {
        global $header;
        $header = [];
        $_SERVER['IF_MODIFIED_SINCE'] = '2011-11-11 11:11:11';
        $requestBoth = new Request(new ServerRequest('get', '/users/alganet'));
        $this->object->get('/users/*', function() {
                return 'hi!';
            })->lastModified(
            function() {
                return new \DateTime('2011-11-11 11:11:12');
            });
        $r = (string) $this->object->dispatchRequest($requestBoth)->response()->getBody();
        $this->assertEquals('hi!', $r);
        $this->assertContains('Last-Modified: Fri, 11 Nov 2011 11:11:12 +0000', $header);
        $this->assertNotContains('HTTP/1.1 304 Not Modified', $header);
    }

    public function testLastModifiedSince2()
    {
        global $header;
        $header = [];
        $_SERVER['IF_MODIFIED_SINCE'] = '2011-11-11 11:11:11';
        $requestBoth = new Request(new ServerRequest('get', '/users/alganet'));
        $this->object->get('/users/*', function() {
                return 'hi!';
            })->lastModified(
            function() {
                return new \DateTime('2011-11-11 11:11:10');
            });
        $r = (string) $this->object->dispatchRequest($requestBoth)->response()->getBody();
        $this->assertEquals('', $r);
        $this->assertContains('Last-Modified: Fri, 11 Nov 2011 11:11:10 +0000', $header);
        $this->assertContains('HTTP/1.1 304 Not Modified', $header);
    }

    public function testLastModifiedSince3()
    {
        global $header;
        $header = [];
        $_SERVER['IF_MODIFIED_SINCE'] = '2011-11-11 11:11:11';
        $requestBoth = new Request(new ServerRequest('get', '/users/alganet'));
        $this->object->get('/users/*', function() {
                return 'hi!';
            })->lastModified(
            function() {
                return new \DateTime('2011-11-11 11:11:11');
            });
        $r = (string) $this->object->dispatchRequest($requestBoth)->response()->getBody();
        $this->assertEquals('', $r);
        $this->assertContains('Last-Modified: Fri, 11 Nov 2011 11:11:11 +0000', $header);
        $this->assertContains('HTTP/1.1 304 Not Modified', $header);
    }

    public function testContenType()
    {
        $_SERVER['CONTENT_TYPE'] = 'text/xml';
        $requestBoth = new Request(new ServerRequest('get', '/users/alganet'));
        $result = null;
        $this->object->get('/users/*', function() {

            })->contentType([
            'text/json' => function() {

            },
            'text/xml' => function() use (&$result) {
                $result = 'ok';
            }]);
        $r = $this->object->dispatchRequest($requestBoth)->response();
        $this->assertEquals('ok', $result);
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
        $this->assertTrue($ok);
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
        $this->assertTrue($ok);
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
        $this->assertTrue($ok);
    }

    public function testCreateUri()
    {
        $r = new Router(new Psr17Factory());
        $ro = $r->any('/users/*/test/*', function() {

            });
        $this->assertEquals(
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
        $this->assertEquals('alganet', $response);
    }

    /**
     * @group issues
     * @ticket 37
     **/
    public function test_optional_parameter_in_class_routes()
    {
        $r = new Router(new Psr17Factory());
        $r->any('/optional/*', 'Respect\Rest\MyOptionalParamRoute');
        $response = (string) $r->dispatch(new ServerRequest('get', '/optional'))->response()->getBody();
        $this->assertEquals('John Doe', $response);
    }
}
if (!function_exists(__NAMESPACE__.'\\header')) {
    function header($string, $replace=true, $http_response_code=200)
    {
        global $header;
        if (!$replace && isset($header))
            return;

        $header[$string] = $string;
        return @\header($string);
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

    protected $params = [];

    public function __construct()
    {
        $this->params = func_get_args();
        return 'whoops';
    }

    public function get($user)
    {
        return [$user, 'get', $this->params];
    }

    public function post($user)
    {
        return [$user, 'post', $this->params];
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
    $header=[];
}
