<?php
namespace Respect\Rest\Routines{

use Respect\Rest\Request;
/**
 * @covers Respect\Rest\Routines\UserAgent
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class UserAgentTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var UserAgent
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
            $_SERVER['REQUEST_URI'] = '/';
            $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->object = new UserAgent(array(
            'FIREFOX' => function (){ return 'ff';},
            'InhernetExplorer' => function (){ return 'ie';},
            'a' => function (){ return 'a';},
            'o' => function (){ return 'o';},
            'e' => function (){ return 'e';},
            'u' => function (){ return 'u';},
        ));

    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
        unset($this->object);
    }


    /**
     * @covers Respect\Rest\Routines\UserAgent::through
     */
    public function testThrough()
    {global $headers;
        $request = @new Request();
        $params = array();
        $alias = &$this->object;
        $_SERVER['HTTP_USER_AGENT'] = 'FIREFOX';
        $this->assertTrue($alias->when($request, $params));
        $this->assertInstanceOf('Closure', $alias->through($request, $params));
        $this->setUp();
        $_SERVER['HTTP_USER_AGENT'] = 'InhernetExplorer';
        $this->assertTrue($alias->when($request, $params));
        $this->assertInstanceOf('Closure', $alias->through($request, $params));
        $this->setUp();
        $_SERVER['HTTP_USER_AGENT'] = 'CHROME';
        $this->assertFalse($alias->when($request, $params));
        $this->assertNull($alias->through($request, $params));
    }
}
    if (!function_exists(__NAMESPACE__.'\\header')) {
        function header($string, $replace=true, $http_response_code=200)
        {echo "hedaec1 $string\n";
            global $header;
            if (!$replace && isset($header))
                return;

            $header[$string] = $string;
        }
    }
}

namespace Respect\Rest {
    if (!function_exists(__NAMESPACE__.'\\header')) {
        function header($string, $replace=true, $http_response_code=200)
        {echo "hedaec2 $string\n";
            global $header;
            if (!$replace && isset($header))
                return;

            $header[$string] = $string;
        }
    }
}

namespace {
    $header=array('a');
}
