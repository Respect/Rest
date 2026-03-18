<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routines\Routinable;

class DummyRoutine implements Routinable
{
    public static string $result = '';

    public function __construct(string $param1, string $param2, string $param3)
    {
        static::$result = $param1 . ', ' . $param2 . ', ' . $param3;
    }
}
