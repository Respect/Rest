<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

use function array_keys;
use function array_pop;
use function array_slice;
use function arsort;
use function explode;
use function preg_replace;
use function str_replace;
use function str_starts_with;
use function stripos;
use function strpos;
use function strtolower;
use function substr;
use function trim;
use function ucwords;

/** Base class for content-negotiation */
// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousAbstractClassNaming.SuperfluousPrefix
abstract class AbstractAccept extends AbstractCallbackMediator implements
    ProxyableBy,
    ProxyableThrough,
    Unique,
    IgnorableFileExtension
{
    public const string ACCEPT_HEADER = '';

    protected string $requestUri = '';

    /** @param array<int, mixed> $params */
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
            array_pop($unsyncedParams),
        );
        $request->params = $unsyncedParams;

        return null;
    }

    /** @param array<int, mixed> $params */
    public function through(Request $request, array $params): mixed
    {
        return $this->getNegotiatedCallback($request);
    }

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
        // phpcs:ignore SlevomatCodingStandard.Classes.DisallowLateStaticBindingForConstants
        $header = static::ACCEPT_HEADER;

        if (!str_starts_with($header, 'HTTP_')) {
            return $header;
        }

        return str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($header, 5)))));
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return array<int, string>
     */
    protected function identifyRequested(Request $request, array $params): array
    {
        $this->requestUri = $request->uri;

        $headerName = $this->getAcceptHeaderName();
        $acceptHeader = $request->serverRequest->getHeaderLine($headerName);

        if ($acceptHeader === '') {
            return ['*'];
        }

        $acceptList = [];
        foreach (explode(',', $acceptHeader) as $index => $acceptPart) {
            $requested = $this->normalizeRequested($acceptPart);
            if ($requested === '') {
                continue;
            }

            $acceptList[$requested] = $this->extractQuality($acceptPart, $index);
        }

        arsort($acceptList);

        return array_keys($acceptList);
    }

    /** @return array<int, string> */
    protected function considerProvisions(string $requested): array
    {
        return $this->getKeys(); // no need to split see authorize
    }

    /** @param array<int, mixed> $params */
    protected function notifyApproved(string $requested, string $provided, Request $request, array $params): void
    {
        $this->rememberNegotiatedCallback($request, $this->getCallback($provided));

        if (strpos($provided, '.') !== false) {
            return;
        }

        $headerType = $this->getNegotiatedHeaderType();

        $contentHeader = 'Content-Type';
        if (strpos($headerType, '-') !== false) {
            $contentHeader = str_replace('Accept', 'Content', $headerType);
        }

        $request->setResponseHeader($contentHeader, $provided);
        $request->appendResponseHeader('Vary', 'negotiate');
        $request->appendResponseHeader('Vary', strtolower($headerType));
        $request->defaultResponseHeader(
            'Content-Location',
            (string) $request->serverRequest->getUri()->getPath(),
        );
    }

    /** @param array<int, mixed> $params */
    protected function notifyDeclined(string $requested, string $provided, Request $request, array $params): void
    {
        $this->forgetNegotiatedCallback();
        $request->prepareResponse(406);
    }

    protected function authorize(string $requested, string $provided): mixed
    {
        // negotiate on file extension
        if (strpos($provided, '.') !== false) {
            return stripos($this->requestUri, $provided) !== false;
        }

        if ($requested === '*') {
            return true;
        }

        // normal matching requirements
        return $requested == $provided;
    }

    protected function getNegotiatedHeaderType(): string
    {
        return (string) preg_replace(
            [
                '/(^.*)(?=\w*$)/U',
                '/(?!^)([A-Z]+)/',
            ],
            ['', '-$1'],
            static::class,
        );
    }

    private function extractQuality(string $acceptPart, int $index): float
    {
        foreach (array_slice(explode(';', $acceptPart), 1) as $parameter) {
            $parameter = trim($parameter);
            if (!str_starts_with(strtolower($parameter), 'q=')) {
                continue;
            }

            return (float) substr($parameter, 2);
        }

        return (10000 - $index) / 10000;
    }

    private function normalizeRequested(string $requested): string
    {
        return trim(explode(';', $requested, 2)[0]);
    }
}
