<?php
namespace Respect\Rest\Routines {

use Respect\Rest\Request;
/**
 * @covers Respect\Rest\Routines\LastModified
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class LastModifiedTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var LastModified
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        global $header;
        $header = array();
        unset($_SERVER['IF_MODIFIED_SINCE']);
        $this->object = new LastModified(function () {
                return new \DateTime('2011-11-11 11:11:12');

            });
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
     * @covers Respect\Rest\Routines\LastModified::by
     */
    public function testBy()
    {
        global $header;
        $request = @new Request();
        $params = array();
        $alias = &$this->object;
        $this->assertTrue($alias->by($request, $params));
        $this->assertCount(0, $header);

        $_SERVER['IF_MODIFIED_SINCE'] = '2011-11-11 11:11:11';

        $this->assertNull($alias->by($request, $params));
        $this->assertArrayHasKey('Last-Modified: Fri, 11 Nov 2011 11:11:12 +0000',
                                $header);
        $this->assertNotContains('HTTP/1.1 304 Not Modified', $header);


        $_SERVER['IF_MODIFIED_SINCE'] = '2011-11-11 11:11:13';
        $this->assertSame(false, $alias->by($request, $params));
        $this->assertArrayHasKey('HTTP/1.1 304 Not Modified', $header);
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
