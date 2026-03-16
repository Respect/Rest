<?php
declare(strict_types=1);

namespace Stubs\Routines;

final class WhenAlwaysTrue
{
    public $invoked = false;

    public function __invoke()
    {
        $this->invoked = true;
        return true;
    }
}