<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routes;

use ReflectionMethod;
use InvalidArgumentException;
use Respect\Rest\Routable;

class Instance extends AbstractRoute
{
    public $class = '';
    protected $instance = null;
    /** @var ReflectionMethod */
    protected $reflection;

    public function __construct($method, $pattern, $instance)
    {
        $this->instance = $instance;
        $this->class = get_class($instance);
        parent::__construct($method, $pattern);
    }

    public function getReflection($method)
    {
        if (empty($this->reflection)) {
            $this->reflection = new ReflectionMethod(
                $this->instance,
                $method
            );
        }

        return $this->reflection;
    }

    public function runTarget($method, &$params)
    {
        if (!$this->instance instanceof Routable) {
            throw new InvalidArgumentException('Route target must be an instance of Respect\Rest\Routable');
        }

        return call_user_func_array(
            array($this->instance, $method),
            $params
        );
    }
}
