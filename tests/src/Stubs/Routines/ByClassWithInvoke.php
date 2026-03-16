<?php
declare(strict_types=1);

namespace Stubs\Routines;

final class ByClassWithInvoke
{
    public $invoked = false;

    public function __invoke()
    {
        $this->invoked = true;
        return __CLASS__;
    }
}