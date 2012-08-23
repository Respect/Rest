<?php

namespace Respect\Rest;

use Respect\Rest\Param;

class ParamTest extends \PHPUnit_Framework_TestCase
{

    public function test_isType_method()
    {
        $get = new Param(Param::GET);
        $get_reflection = new \ReflectionMethod($get, 'isType');
        $get_reflection->setAccessible(true);
        $this->assertTrue($get_reflection->invoke($get, Param::GET));

        $post = new Param(Param::POST);
        $post_reflection = new \ReflectionMethod($post, 'isType');
        $post_reflection->setAccessible(true);
        $this->assertTrue($post_reflection->invoke($post, Param::POST));

        $cookie = new Param(Param::COOKIE);
        $cookie_reflection = new \ReflectionMethod($cookie, 'isType');
        $cookie_reflection->setAccessible(true);
        $this->assertTrue($cookie_reflection->invoke($cookie, Param::COOKIE));
    }

    public function test_hasType_method()
    {
        $get_post_cookie = new Param(Param::GET | Param::POST | Param::COOKIE);
        $get_post_cookie_reflection = new \ReflectionMethod($get_post_cookie, 'hasType');
        $get_post_cookie_reflection->setAccessible(true);
        $this->assertTrue($get_post_cookie_reflection->invoke($get_post_cookie, Param::GET));
        $this->assertTrue($get_post_cookie_reflection->invoke($get_post_cookie, Param::POST));
        $this->assertTrue($get_post_cookie_reflection->invoke($get_post_cookie, Param::COOKIE));

        $get_post = new Param(Param::GET | Param::POST);
        $get_post_reflection = new \ReflectionMethod($get_post_cookie, 'hasType');
        $get_post_reflection->setAccessible(true);
        $this->assertTrue($get_post_reflection->invoke($get_post, Param::GET));
        $this->assertTrue($get_post_reflection->invoke($get_post, Param::POST));
        $this->assertFalse($get_post_reflection->invoke($get_post, Param::COOKIE));

        $get_cookie = new Param(Param::GET | Param::POST);
        $get_cookie_reflection = new \ReflectionMethod($get_post_cookie, 'hasType');
        $get_cookie_reflection->setAccessible(true);
        $this->assertTrue($get_cookie_reflection->invoke($get_cookie, Param::GET));
        $this->assertTrue($get_cookie_reflection->invoke($get_cookie, Param::POST));
        $this->assertFalse($get_cookie_reflection->invoke($get_cookie, Param::COOKIE));

    }
    
    public function test_get_value()
    {
        $expected_1 = 3456789;
        $_REQUEST['POST'] = array('foo' => $expected_1);
        
        $param_1 = new Param(Param::POST);
        $this->assertEquals($expected_1, $param_1->getValue('foo'));

        $expected_2 = 12345;
        $_REQUEST['GET'] = array('foo' => $expected_2 - 1);
        $_REQUEST['POST'] = array('foo' => $expected_2);
        $_REQUEST['COOKIE'] = array('foo' => $expected_2 + 1);
        
        $param_2 = new Param(Param::POST);
        $this->assertEquals($expected_2, $param_2->getValue('foo'));
    }
    
    public function test_get_values()
    {
        $expected = array('foo' => 9865676);
        $_REQUEST['GET'] = array('foo' => '$expected - 1');
        $_REQUEST['POST'] = $expected;
        $_REQUEST['COOKIE'] = array('foo' => '$expected + 1');
        
        $param = new Param(Param::POST);
        $this->assertEquals($expected, $param->getValues());
    }
    
    public function test_isGet_method()
    {
        $param = new Param(Param::GET);
        $this->assertTrue($param->isGet());        
    }

    public function test_isPost_method()
    {
        $param = new Param(Param::POST);
        $this->assertTrue($param->isPost());        
    }

    public function test_isCookie_method()
    {
        $param = new Param(Param::COOKIE);
        $this->assertTrue($param->isCookie());        
    }

    public function test_hasGet_method()
    {
        $param = new Param(Param::GET);
        $this->assertTrue($param->hasGet());        
    }

    public function test_hasPost_method()
    {
        $param = new Param(Param::POST);
        $this->assertTrue($param->hasPost());        
    }

    public function test_hasCookie_method()
    {
        $param = new Param(Param::COOKIE);
        $this->assertTrue($param->hasCookie());        
    }

}