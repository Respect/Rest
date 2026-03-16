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

/** Base class for content-negotiation */
abstract class AbstractAccept extends AbstractCallbackMediator implements
    ProxyableBy,
    ProxyableThrough,
    Unique,
    IgnorableFileExtension
{
    protected $negotiated = null;
    protected $request_uri;

    protected function identifyRequested(Request $request, $params)
    {
        $this->request_uri = $request->uri;

        if (!isset($_SERVER[static::ACCEPT_HEADER])) {
            return array();
        }
        $acceptHeader = $_SERVER[static::ACCEPT_HEADER];
        $acceptParts = explode(',', $acceptHeader);
        $acceptList = array();
        foreach ($acceptParts as $k => &$acceptPart) {
            $parts = explode(';q=', trim($acceptPart));
            $provided = array_shift($parts);
            $quality = array_shift($parts) ?: (10000 - $k) / 10000;
            $acceptList[$provided] = $quality;
        }
        arsort($acceptList);

        return array_keys($acceptList);
    }
    protected function considerProvisions($requested)
    {
        return $this->getKeys(); // no need to split see authorize
    }
    protected function notifyApproved($requested, $provided, Request $request, $params)
    {
        $this->negotiated = new SplObjectStorage();
        $this->negotiated[$request] = $this->getCallback($provided);
        if (false === strpos($provided, '.')) {
            $header_type = preg_replace(
                array(
                    '/(^.*)(?=\w*$)/U', // select namespace to strip
                    '/(?!^)([A-Z]+)/',   // select camels to add -
                ),
                array('', '-$1'),
                get_class($this)
            );

            $content_header = 'Content-Type';

            if (false !== strpos($header_type, '-')) {
                $content_header = str_replace('Accept', 'Content', $header_type);
            }

            header("$content_header: $provided");                   // RFC 2616
            header("Vary: negotiate,".strtolower($header_type));    // RFC 2616/2295
            header("Content-Location: {$_SERVER['REQUEST_URI']}");  // RFC 2616
            header('Expires: Thu, 01 Jan 1980 00:00:00 GMT');       // RFC 2295
            header('Cache-Control: max-age=86400');                 // RFC 2295
        }
    }
    protected function notifyDeclined($requested, $provided, Request $request, $params)
    {
        $this->negotiated = false;
        header('HTTP/1.1 406');
    }

    protected function authorize($requested, $provided)
    {
        // negotiate on file extension
        if (false !== strpos($provided, '.')) {
            if (false !== stripos($this->request_uri, $provided)) {
                return true;
            }
        }

        // normal matching requirements
        return $requested == $provided;
    }

    public function by(Request $request, $params)
    {
        $unsyncedParams = $request->params;
        $extensions = $this->filterKeysContain('.');

        if (empty($extensions) || empty($unsyncedParams)) {
            return;
        }

        $unsyncedParams[] = str_replace(
            $extensions,
            '',
            array_pop($unsyncedParams)
        );
        $request->params = $unsyncedParams;
    }

    public function through(Request $request, $params)
    {
        if (!isset($this->negotiated[$request])
            || false === $this->negotiated[$request]) {
            return;
        }

        return $this->negotiated[$request];
    }
}
