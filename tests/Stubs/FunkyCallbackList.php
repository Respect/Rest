<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routines\CallbackList;

class FunkyCallbackList extends CallbackList
{
    public function funkyExecuteCallback($key, $params)
    {
        return $this->executeCallback($key, $params);
    }

    public function funkyGetCallback($key)
    {
        return $this->getCallback($key);
    }
}
