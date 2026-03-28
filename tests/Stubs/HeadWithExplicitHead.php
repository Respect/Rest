<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

final class HeadWithExplicitHead implements Routable
{
    public function get(): string
    {
        return 'get-response';
    }

    public function head(): string
    {
        return 'head-response';
    }
}
