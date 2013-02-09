<?php

namespace Stubs\Routines;

use Respect\Rest\Routines\AbstractRoutine as RestAbstractRoutine;
use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;
use Respect\Rest\Routes\AbstractRoute;

/**
 * Concrete stub class for AbstractRoutines.
 */
class AbstractRoutine extends RestAbstractRoutine
{
    public function getCallback()
    {
        return $this->callback;
    }
}
