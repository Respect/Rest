<?php

declare(strict_types=1);

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
    const string ACCEPT_HEADER = '';

    protected SplObjectStorage|false|null $negotiated = null;
    protected string $request_uri = '';

    /**
     * Convert a $_SERVER-style header constant to a PSR-7 header name.
     *
     * HTTP_ACCEPT          -> Accept
     * HTTP_ACCEPT_CHARSET  -> Accept-Charset
     * HTTP_ACCEPT_ENCODING -> Accept-Encoding
     * HTTP_ACCEPT_LANGUAGE -> Accept-Language
     * HTTP_USER_AGENT      -> User-Agent
     */
    protected function getAcceptHeaderName(): string
    {
        $header = static::ACCEPT_HEADER;

        if (!str_starts_with($header, 'HTTP_')) {
            return $header;
        }

        return str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($header, 5)))));
    }

    protected function identifyRequested(Request $request, array $params): array
    {
        $this->request_uri = $request->uri;

        $headerName = $this->getAcceptHeaderName();
        $acceptHeader = $request->serverRequest->getHeaderLine($headerName);

        if ($acceptHeader === '') {
            return [];
        }

        $acceptParts = explode(',', $acceptHeader);
        $acceptList = [];
        foreach ($acceptParts as $k => &$acceptPart) {
            $parts = explode(';q=', trim($acceptPart));
            $provided = array_shift($parts);
            $quality = array_shift($parts) ?: (10000 - $k) / 10000;
            $acceptList[$provided] = $quality;
        }
        arsort($acceptList);

        return array_keys($acceptList);
    }

    protected function considerProvisions(string $requested): array
    {
        return $this->getKeys(); // no need to split see authorize
    }

    protected function notifyApproved(string $requested, string $provided, Request $request, array $params): void
    {
        $this->negotiated = new SplObjectStorage();
        $this->negotiated[$request] = $this->getCallback($provided);

        if (false === strpos($provided, '.')) {
            $headerType = preg_replace(
                [
                    '/(^.*)(?=\w*$)/U',
                    '/(?!^)([A-Z]+)/',
                ],
                ['', '-$1'],
                get_class($this)
            );

            $contentHeader = 'Content-Type';
            if (false !== strpos($headerType, '-')) {
                $contentHeader = str_replace('Accept', 'Content', $headerType);
            }

            $request->responseHeaders[$contentHeader] = $provided;
            $request->responseHeaders['Vary'] = 'negotiate,' . strtolower($headerType);
            $request->responseHeaders['Content-Location'] = (string) $request->serverRequest->getUri()->getPath();
            $request->responseHeaders['Expires'] = 'Thu, 01 Jan 1980 00:00:00 GMT';
            $request->responseHeaders['Cache-Control'] = 'max-age=86400';
        }
    }

    protected function notifyDeclined(string $requested, string $provided, Request $request, array $params): void
    {
        $this->negotiated = false;
        $request->responseStatus = 406;
    }

    protected function authorize(string $requested, string $provided): mixed
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

    public function by(Request $request, array $params): mixed
    {
        $unsyncedParams = $request->params;
        $extensions = $this->filterKeysContain('.');

        if (empty($extensions) || empty($unsyncedParams)) {
            return null;
        }

        $unsyncedParams[] = str_replace(
            $extensions,
            '',
            array_pop($unsyncedParams)
        );
        $request->params = $unsyncedParams;

        return null;
    }

    public function through(Request $request, array $params): mixed
    {
        if (!isset($this->negotiated[$request])
            || false === $this->negotiated[$request]) {
            return null;
        }

        return $this->negotiated[$request];
    }
}
