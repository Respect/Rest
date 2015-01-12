<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routes;

class Exception extends Callback
{
    public $class;
    public $callback;
    public $exception;

    public function __construct($class, $callback)
    {
        $this->class = $class;
        parent::__construct('ANY', '^$', $callback);
    }

    public function runTarget($method, &$params)
    {
        return call_user_func($this->callback, $this->exception);
    }
}
