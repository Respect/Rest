<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

class AuthBasic extends AbstractRoutine implements ProxyableBy
{
    public $realm;

    public function __construct($realm, $callback)
    {
        $this->realm = $realm;
        parent::__construct($callback);
    }

    public function by(Request $request, $params)
    {
        $callbackResponse = false;

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $callbackResponse = call_user_func_array(
                $this->callback,
                array_merge(explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6))), $params)
            );
        } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
            $callbackResponse = call_user_func_array(
                $this->callback,
                array_merge(array($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']), $params)
            );
        }

        if ($callbackResponse === false) {
            header('HTTP/1.1 401');
            header("WWW-Authenticate: Basic realm=\"{$this->realm}\"");

            return call_user_func($this->callback, null, null);
        }

        return $callbackResponse;
    }
}
