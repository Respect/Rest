<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

final class HeadFactoryController implements Routable
{
    public function get(): string
    {
        return 'factory';
    }
}
