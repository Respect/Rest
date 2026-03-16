<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

use SplObjectStorage;
use Respect\Rest\Request;

/** Handles User Agent filters */
class UserAgent extends AbstractCallbackMediator implements ProxyableThrough, Unique
{
    const ACCEPT_HEADER = 'HTTP_USER_AGENT';
    private $negotiated = false;

    protected function identifyRequested(Request $request, $params)
    {
        return array($_SERVER[self::ACCEPT_HEADER]);
    }
    protected function considerProvisions($requested)
    {
        return $this->getKeys();
    }
    protected function notifyApproved($requested, $provided, Request $request, $params)
    {
        $this->negotiated = new SplObjectStorage();
        $this->negotiated[$request] = $this->getCallback($provided);
    }
    protected function notifyDeclined($requested, $provided, Request $request, $params)
    {
        $this->negotiated = false;
    }

    protected function authorize($requested, $provided)
    {
        if ($provided === '*' || preg_match("#$provided#", $requested)) {
            return true;
        }

        return false;
    }

    public function through(Request $request, $params)
    {
        if (false !== $this->negotiated) {
            return $this->negotiated[$request];
        }
    }
}
