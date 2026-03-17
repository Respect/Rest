<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Request;
use Respect\Rest\Routines\Through;

/**
 * @covers Respect\Rest\Routines\Through
 */
final class ThroughTest extends TestCase
{
    protected $object;

    protected function setUp(): void
    {
        $this->object = new Through(function () {
            return 'from through callback';
            });
    }

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
