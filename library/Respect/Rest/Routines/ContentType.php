<?php

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
        return isset($_SERVER['CONTENT_TYPE'])? array($_SERVER['CONTENT_TYPE']) : array();
    }
    protected function considerProvisions($requested)
    {
        return $this->getKeys();
    }
    protected function notifyApproved($requested, $provided, Request $request, $params)
    {
        $this->negotiated = new SplObjectStorage;;
        $this->negotiated[$request] = $this->getCallback($provided);
    }
    protected function notifyDeclined($requested, $provided, Request $request, $params)
    {
        if (isset($this->negotiated))
             $this->negotiated[$request] = null;
        else
             $this->negotiated = null;
    }

    public function by(Request $request, $params)
    {
        if (!isset($this->negotiated[$request])
            || false === $this->negotiated[$request])

            return;

        return call_user_func($this->negotiated[$request]);
    }
}
