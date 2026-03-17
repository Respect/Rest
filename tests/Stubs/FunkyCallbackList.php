<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routines\CallbackList;

class FunkyCallbackList extends CallbackList
{
    /** @param array<int, mixed> $params */
    public function funkyExecuteCallback(string $key, array $params): mixed
    {
        return $this->executeCallback($key, $params);
    }

    public function funkyGetCallback(string $key): callable
    {
        return $this->getCallback($key);
    }
}
