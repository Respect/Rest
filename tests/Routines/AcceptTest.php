<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\HttpFactories;
use Respect\Rest\Routines\AbstractAccept;
use Respect\Rest\Routines\Accept;

/** @covers Respect\Rest\Routines\Accept */
/** @covers Respect\Rest\Routines\AbstractAccept */
final class AcceptTest extends TestCase
{
    private HttpFactories $httpFactories;

    private Accept $accept;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->httpFactories = new HttpFactories($factory, $factory);
        $this->accept = new Accept([
            'text/html' => static fn(): string => 'html',
            'application/json' => static fn(): string => 'json',
            'text/plain' => static fn(): string => 'plain',
        ]);
    }

    public function testExactMimeMatch(): void
    {
        $params = [];
        $context = $this->newContext('Accept', 'application/json');

        self::assertTrue($this->accept->when($context, $params));
        self::assertSame('application/json', $context->response()?->getHeaderLine('Content-Type'));
    }

    public function testWildcardMatchesFirstProvided(): void
    {
        $params = [];
        $context = $this->newContext('Accept', '*/*');

        self::assertTrue($this->accept->when($context, $params));
    }

    public function testSubtypeWildcard(): void
    {
        $params = [];
        $context = $this->newContext('Accept', 'text/*');

        self::assertTrue($this->accept->when($context, $params));
    }

    public function testQualityFactorOrdering(): void
    {
        $params = [];
        $context = $this->newContext('Accept', 'text/html;q=0.5, application/json;q=0.9');

        self::assertTrue($this->accept->when($context, $params));

        $callback = $this->accept->through($context, $params);
        self::assertNotNull($callback);
        self::assertSame('json', $callback('ignored'));
    }

    public function testNotAcceptableWhenNoMatch(): void
    {
        $params = [];
        $context = $this->newContext('Accept', 'image/png');

        self::assertFalse($this->accept->when($context, $params));
        self::assertTrue($context->hasPreparedResponse());
        self::assertSame(406, $context->response()?->getStatusCode());
    }

    public function testMissingAcceptHeaderDefaultsToWildcard(): void
    {
        $params = [];
        $context = new DispatchContext(
            new ServerRequest('GET', '/'),
            $this->httpFactories->responses,
            $this->httpFactories->streams,
        );

        self::assertTrue($this->accept->when($context, $params));
    }

    public function testContentLocationDefaultHeaderSetOnNegotiation(): void
    {
        $params = [];
        $context = $this->newContext('Accept', 'text/html');

        self::assertTrue($this->accept->when($context, $params));
        self::assertArrayHasKey('Content-Location', $context->defaultResponseHeaders);
    }

    public function testThroughReturnsNegotiatedCallback(): void
    {
        $params = [];
        $context = $this->newContext('Accept', 'text/html');

        $this->accept->when($context, $params);
        $callback = $this->accept->through($context, $params);

        self::assertNotNull($callback);
        self::assertSame('html', $callback('ignored'));
    }

    public function testThroughReturnsNullWhenNoNegotiation(): void
    {
        $params = [];
        $context = $this->newContext('Accept', 'image/png');

        $this->accept->when($context, $params);
        $callback = $this->accept->through($context, $params);

        self::assertNull($callback);
    }

    public function testNonHttpPrefixedHeaderIsUsedDirectly(): void
    {
        $routine = new class ([
            'gzip' => static fn(): string => 'compressed',
        ]) extends AbstractAccept {
            public const string ACCEPT_HEADER = 'X-Custom-Accept';
        };

        $params = [];
        $context = new DispatchContext(
            (new ServerRequest('GET', '/'))->withHeader('X-Custom-Accept', 'gzip'),
            $this->httpFactories->responses,
            $this->httpFactories->streams,
        );

        self::assertTrue($routine->when($context, $params));
    }

    private function newContext(string $header, string $value): DispatchContext
    {
        return new DispatchContext(
            (new ServerRequest('GET', '/'))->withHeader($header, $value),
            $this->httpFactories->responses,
            $this->httpFactories->streams,
        );
    }
}
