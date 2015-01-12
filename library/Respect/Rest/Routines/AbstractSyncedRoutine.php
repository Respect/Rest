<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

use ReflectionFunction;
use ReflectionMethod;
use ReflectionObject;
use ReflectionClass;
use Closure;
use Respect\Rest\Request;

/** Base class for routines that sync parameters */
abstract class AbstractSyncedRoutine extends AbstractRoutine implements ParamSynced
{
    /**
     * @var Reflector
     */
    protected $reflection;

    /**
     * Return parameters that can be used with the routine.
     *
     * @return array
     */
    public function getParameters()
    {
        $reflection = $this->getReflection();
        if (!$reflection instanceof ReflectionObject && !$reflection instanceof ReflectionClass) {
            return $this->getReflection()->getParameters();
        }

        return array();
    }

    /**
     * Executes the routine and return its result.
     *
     * @param  Respect\Rest\Request $request
     * @param  array                $params
     * @return mixed
     */
    public function execute(Request $request, $params)
    {
        $callback = $this->getCallback();
        if (is_string($callback)) {
            $reflection      = $this->getReflection();
            $routineInstance = $reflection->newInstanceArgs($params);

            return $routineInstance();
        }

        return call_user_func_array($callback, $params);
    }

    /**
     * Returns a concrete ReflectionFunctionAbstract for this routine callback.
     *
     * @return Reflector
     */
    protected function getReflection()
    {
        $callback = $this->getCallback();
        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        } elseif ($callback instanceof Closure) {
            return new ReflectionFunction($callback);
        } elseif (is_string($callback)) {
            return new ReflectionClass($callback);
        } else {
            return new ReflectionObject($callback);
        }
    }
}
