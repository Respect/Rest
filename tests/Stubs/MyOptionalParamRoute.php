<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

class MyOptionalParamRoute implements Routable
{
    public function get($user = null)
    {
        return $user ?: 'John Doe';
    }
}
