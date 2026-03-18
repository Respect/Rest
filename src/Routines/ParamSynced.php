<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use ReflectionParameter;

/** Callback Routine that sync params */
interface ParamSynced
{
    /**
     * Returns parameters for the callback
     *
     * @return array<int, ReflectionParameter>
     */
    public function getParameters(): array;
}
