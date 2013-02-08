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
        $callback = $this->getCallback();
        if (is_array($callback))
            return new ReflectionMethod($callback[0], $callback[1]);
        else
            return new ReflectionFunction($callback);
    }

}
