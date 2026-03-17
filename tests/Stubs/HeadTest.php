<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

class HeadTest implements Routable
{
    private $expectedHeader;

    public function __construct($expectedHeader)
    {
        $this->expectedHeader = $expectedHeader;
    }

    public function get()
    {
        return 'ok';
    }
}
