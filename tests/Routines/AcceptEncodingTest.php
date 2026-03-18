<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\HttpFactories;
use Respect\Rest\Routines\AcceptEncoding;

/** @covers Respect\Rest\Routines\AcceptEncoding */
final class AcceptEncodingTest extends TestCase
{
    private HttpFactories $httpFactories;

    private AcceptEncoding $routine;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->httpFactories = new HttpFactories($factory, $factory);
        $this->routine = new AcceptEncoding([
            'gzip' => static fn(): string => 'gzipped',
            'identity' => static fn(): string => 'plain',
        ]);
    }

    public function testExactEncodingMatch(): void
    {
        $params = [];
        $context = $this->newContext('Accept-Encoding', 'gzip');

        self::assertTrue($this->routine->when($context, $params));
    }

    public function testWildcardMatchesAnyEncoding(): void
    {
        $params = [];
        $context = $this->newContext('Accept-Encoding', '*');

        self::assertTrue($this->routine->when($context, $params));
    }

    public function testNotAcceptableOnMismatch(): void
    {
        $params = [];
        $context = $this->newContext('Accept-Encoding', 'br');

        self::assertFalse($this->routine->when($context, $params));
        self::assertTrue($context->hasPreparedResponse());
        self::assertSame(406, $context->response()?->getStatusCode());
    }

    public function testMissingHeaderDefaultsToWildcard(): void
    {
        $params = [];
        $context = new DispatchContext(
            new ServerRequest('GET', '/'),
            $this->httpFactories->responses,
            $this->httpFactories->streams,
        );

        self::assertTrue($this->routine->when($context, $params));
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
