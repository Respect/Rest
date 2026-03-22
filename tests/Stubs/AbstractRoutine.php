<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routines\AbstractRoutine as RestAbstractRoutine;

/**
 * Concrete stub class for AbstractRoutines.
 */
class AbstractRoutine extends RestAbstractRoutine
{
    public function getCallback(): callable
    {
        return $this->callback;
    }
}
