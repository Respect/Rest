<?php

namespace Respect\Rest\Routines;

use SplObjectStorage;
use Respect\Rest\Request;

/** Handles content type content negotiation */
class ContentType extends AbstractRoutine implements ProxyableWhen, ProxyableBy, Unique
{

    protected $contentMap = array();
    protected $negotiated = null;

    public function __construct(array $callbacksPerContentType = array())
    {
        if (!array_filter($callbacksPerContentType, 'is_callable'))
            throw new \Exception(''); //TODO

            $this->negotiated = new SplObjectStorage;
        $this->contentMap = $callbacksPerContentType;
    }

    /** Negotiates the content type with the given request */
    protected function negotiate(Request $request)
    {
        if (!isset($_SERVER['CONTENT_TYPE']))
            return false;

        $requested = $_SERVER['CONTENT_TYPE'];
        foreach ($this->contentMap as $provided => $callback)
            if ($requested == $provided)
                return $this->negotiated[$request] = $callback;

        return false;
    }

    public function by(Request $request, $params)
    {
        if (!isset($this->negotiated[$request])
            || false === $this->negotiated[$request])
            return;

        return call_user_func($this->negotiated[$request]);
    }

    public function when(Request $request, $params)
    {
        return false !== $this->negotiate($request);
    }

}
