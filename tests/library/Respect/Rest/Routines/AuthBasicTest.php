<?php

namespace Respect\Rest\Routines;

class AuthBasicTest extends \PHPUnit_Framework_TestCase
{
    private static $wantedParams;

    public function shunt_wantedParams()
    {
        $this->assertSame(self::$wantedParams, func_get_args(), 'wrong arguments were passed to the routine\'s callback');
    }

    protected function tearDown()
    {
        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
        unset($_SERVER['HTTP_AUTHORIZATION']);
    }

    public function test_pass_all_params_to_callback()
    {

        $user = 'John';
        $pass = 'Doe';
        $param1 = 'abc';
        $param2 = 'def';
        self::$wantedParams = array($user, $pass, $param1, $param2);

        $_SERVER['PHP_AUTH_USER'] = $user;
        $_SERVER['PHP_AUTH_PW'] = $pass;
        unset($_SERVER['HTTP_AUTHORIZATION']);

        // to be able to run assertions in callback, I'm using a method here instead of a closure
        $routine = new AuthBasic('auth realm', array($this, 'shunt_wantedParams'));
        $routine->by(new \Respect\Rest\Request('GET', "/$param1/$param2"), array($param1, $param2));

        unset($_SERVER['PHP_AUTH_USER']);
        unset($_SERVER['PHP_AUTH_PW']);
        $_SERVER['HTTP_AUTHORIZATION'] = 'Basic ' . base64_encode($user . ':' . $pass);

        $routine = new AuthBasic('auth realm', array($this, 'shunt_wantedParams'));
        $routine->by(new \Respect\Rest\Request('GET', "/$param1/$param2"), array($param1, $param2));
    }
}
