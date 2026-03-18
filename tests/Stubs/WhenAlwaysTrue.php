<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

final class WhenAlwaysTrue
{
    public bool $invoked = false;

    public function __invoke(): bool
    {
        $this->invoked = true;

        return true;
    }
}
