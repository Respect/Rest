<?php
declare(strict_types=1);

namespace Respect\Rest\Routines;

use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Factory\Psr17Factory;
use Respect\Rest\Request;

/**
 * @covers Respect\Rest\Routines\Through
 * @author Nick Lombard <github@jigsoft.co.za>
 */
final class ThroughTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var Through
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp(): void
    {
        $this->object = new Through(function () {
            return 'from through callback';
            });
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown(): void
    {
        unset($this->object);
    }

    /**
     * @covers Respect\Rest\Routines\Through::through
     */
    public function testThrough()
    {
        $request = new Request(new ServerRequest('GET', '/'));
        $params = [];
        $alias = &$this->object;
        self::assertEquals('from through callback',
                $alias->through($request, $params));
    }
}
