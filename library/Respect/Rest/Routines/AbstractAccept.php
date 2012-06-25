<?php

namespace Respect\Rest\Routines;

use SplObjectStorage;
use UnexpectedValueException;
use Respect\Rest\Request;

/** Base class for content-negotiation */
abstract class AbstractAccept extends AbstractRoutine implements ProxyableBy, ProxyableWhen, ProxyableThrough, Unique , IgnorableFileExtension
{

    protected $callbacksPerMimeType = array();
    protected $callbacksPerExtension = array();
    protected $negotiated = null;

    public function __construct(array $callbacksPerType = array())
    {
        $this->negotiated = new SplObjectStorage;
        $this->parseAcceptMap($callbacksPerType);
    }

    /** Parses an array of callbacks per accept-type */
    protected function parseAcceptMap(array $callbacksPerType)
    {
        if (!array_filter($callbacksPerType, 'is_callable'))
            throw new UnexpectedValueException('Not a callable argument for Content-Type negotiation.');

            foreach ($callbacksPerType as $acceptSpec => $callback)
            if ('.' === $acceptSpec[0])
                $this->callbacksPerExtension[$acceptSpec] = $callback;
            else
                $this->callbacksPerMimeType[$acceptSpec] = $callback;
    }

    /** Negotiate content with the given Request */
    protected function negotiate(Request $request)
    {
        foreach ($this->callbacksPerExtension as $provided => $callback)
            if (false !== stripos($request->uri, $provided))
                return $this->negotiated[$request] = $callback;

        if (!isset($_SERVER[static::ACCEPT_HEADER]))
            return false;

        $acceptHeader = $_SERVER[static::ACCEPT_HEADER];
        $acceptParts = explode(',', $acceptHeader);
        $acceptList = array();
        foreach ($acceptParts as $k => &$acceptPart) {
            $parts = explode(';q=', trim($acceptPart));
            $provided = array_shift($parts);
            $quality = array_shift($parts) ? : (10000 - $k) / 10000;
            $acceptList[$provided] = $quality;
        }
        arsort($acceptList);
        foreach ($acceptList as $requested => $quality)
            foreach ($this->callbacksPerMimeType as $provided => $callback)
                if (false !== ($accepted = $this->compareItems($requested, $provided))) {
                    $this->negotiated[$request] = $callback;
                    return $this->responseHeaders($accepted);
                }

        return false;
    }

    private function responseHeaders($negotiated) {
        $header_type = preg_replace(
                array(
                        '/(^.*)(?=\w*$)/U', // select namespace to strip
                        '/(?!^)([A-Z]+)/'   // select camels to add -
                     ),
                array('','-$1'), get_class($this));

        $content_header = 'Content-Type';

        if (false !== strpos($header_type, '-'))
            $content_header = str_replace('Accept', 'Content', $header_type);

        header("$content_header: $negotiated");                // RFC 2616
        header("Vary: negotiate,".strtolower($header_type));   // RFC 2616/2295
        header("Content-Location: {$_SERVER['REQUEST_URI']}"); // RFC 2616
        header('Expires: Thu, 01 Jan 1980 00:00:00 GMT');      // RFC 2295
        header('Cache-Control: max-age=86400');                // RFC 2295

        return true;
    }

    public function by(Request $request, $params)
    {
        $unsyncedParams = $request->params;
        $extensions = array_keys($this->callbacksPerExtension);

        if (empty($extensions) || empty($unsyncedParams))
            return;

        $unsyncedParams[] = str_replace(
                $extensions, '', array_pop($unsyncedParams)
        );
        $request->params = $unsyncedParams;
    }

    public function through(Request $request, $params)
    {
        if (!isset($this->negotiated[$request])
            || false === $this->negotiated[$request])
            return;

        return $this->negotiated[$request];
    }

    public function when(Request $request, $params)
    {
        return false !== $this->negotiate($request);
    }

    /** Compares two given content-negotiation elements */
    protected function compareItems($requested, $provided)
    {
        return $requested == $provided;
    }

}
