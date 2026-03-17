<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

final class ByClassWithInvoke
{
    public $invoked = false;

    public function __invoke()
    {
        $this->invoked = true;
        return __CLASS__;
    }
}
