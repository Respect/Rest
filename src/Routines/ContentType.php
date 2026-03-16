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

/** Handles content type content negotiation */
class ContentType extends AbstractCallbackMediator implements ProxyableBy, Unique
{
    protected $contentMap = array();
    protected $negotiated = null;

    protected function identifyRequested(Request $request, $params)
    {
        return isset($_SERVER['CONTENT_TYPE']) ? array($_SERVER['CONTENT_TYPE']) : array();
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

    public function by(Request $request, $params)
    {
        if (false !== $this->negotiated) {
            return call_user_func($this->negotiated[$request]);
        }
    }
}
