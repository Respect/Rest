<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routes;

class Error extends Callback
{
    public $callback;
    public $errors = array();

    public function __construct($callback)
    {
        parent::__construct('ANY', '^$', $callback);
    }

    public function runTarget($method, &$params)
    {
        return call_user_func($this->callback, $this->errors);
    }
}
