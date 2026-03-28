<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routines\CallbackList;

class FunkyCallbackList extends CallbackList
{
    public function funkyGetCallback(string $key): callable
    {
        return $this->getCallback($key);
    }
}
