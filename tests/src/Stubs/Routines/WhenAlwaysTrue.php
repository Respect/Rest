<?php

namespace Stubs\Routines;

class WhenAlwaysTrue
{
    public $invoked = false;

    public function __invoke()
    {
        $this->invoked = true;
        return true;
    }
}