<?php

namespace Respect\Rest\Routines;

use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;
use Respect\Rest\Routes\AbstractRoute;

/** Base class for routines that sync parameters */
abstract class AbstractSyncedRoutine extends AbstractRoutine implements ParamSynced
{

    protected $reflection;

    public function getParameters()
    {
        return $this->getReflection()->getParameters();
    }

    /** Returns a concrete ReflectionFunctionAbstract for this routine callback */
    protected function getReflection()
    {
        if (is_array($this->callback))
            return new ReflectionMethod($this->callback[0], $this->callback[1]);
        else
            return new ReflectionFunction($this->callback);
    }

}
