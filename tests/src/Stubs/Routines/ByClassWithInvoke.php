<?php

namespace Stubs\Routines;

class ByClassWithInvoke
{
    public $invoked = false;

    public function __invoke()
    {
        $this->invoked = true;
        return __CLASS__;
    }
}