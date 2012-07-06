<?php
namespace Respect\Rest\Routines {
use Respect\Rest\Request;
/**
 * @covers Respect\Rest\Routines\When
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class WhenTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var When
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        $this->object = new When(function () {
                return true;
            });
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {
    }

    /**
     * @covers Respect\Rest\Routines\When::when
     */
    public function testWhen()
    {
        global $header;
        $header = array();
        $request = @new Request();
        $params = array();
        $alias = &$this->object;

        $this->assertTrue($alias->when($request, $params));
        $this->assertCount(0, $header);

        $this->object = new When(function () {
                return false;
            });
        $alias = &$this->object;

        $this->assertFalse($alias->when($request, $params));
        $this->assertArrayHasKey('HTTP/1.1 400', $header);
    }
}

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

namespace Respect\Rest {
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
