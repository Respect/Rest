<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

class MyOptionalParamRoute implements Routable
{
    public function get(mixed $user = null): string
    {
        return $user ?: 'John Doe';
    }
}
