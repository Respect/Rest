<?php

namespace Respect\Rest\Routines;

use InvalidArgumentException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use Closure;
use Respect\Rest\Routes\AbstractRoute;

/** Base class for routines that sync parameters */
abstract class AbstractSyncedRoutine extends AbstractRoutine implements ParamSynced
{

    protected $reflection;

    public function getParameters()
    {
        $reflection = $this->getReflection();
        if (!$reflection instanceof ReflectionObject)
            return $this->getReflection()->getParameters();

        $constructorReflection = $reflection->getConstructor();
        if (is_null($constructorReflection))
            return array();
        else
            return $constructorReflection->getParameters();
    }

    /** Returns a concrete ReflectionFunctionAbstract for this routine callback */
    protected function getReflection()
    {
        $callback = $this->getCallback();
        if (is_array($callback))
            return new ReflectionMethod($callback[0], $callback[1]);
        else if ($callback instanceof Closure)
            return new ReflectionFunction($callback);
        else
            return new ReflectionObject($callback);
    }

}
