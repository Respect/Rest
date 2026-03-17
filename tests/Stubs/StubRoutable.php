<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

class StubRoutable implements Routable
{
    public function GET()
    {
        return 'stub response';
    }
}
