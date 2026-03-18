<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routines\AcceptCharset;

/** @covers Respect\Rest\Routines\AcceptCharset */
final class AcceptCharsetTest extends TestCase
{
    private Psr17Factory $factory;

    private AcceptCharset $routine;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->routine = new AcceptCharset([
            'utf-8' => static fn(): string => 'utf8-content',
            'iso-8859-1' => static fn(): string => 'latin1-content',
        ]);
    }

    public function testExactCharsetMatch(): void
    {
        $params = [];
        $context = $this->newContext('Accept-Charset', 'utf-8');

        self::assertTrue($this->routine->when($context, $params));
    }

    public function testWildcardMatchesAnyCharset(): void
    {
        $params = [];
        $context = $this->newContext('Accept-Charset', '*');

        self::assertTrue($this->routine->when($context, $params));
    }

    public function testNotAcceptableOnMismatch(): void
    {
        $params = [];
        $context = $this->newContext('Accept-Charset', 'windows-1252');

        self::assertFalse($this->routine->when($context, $params));
        self::assertTrue($context->hasPreparedResponse());
        self::assertSame(406, $context->response()?->getStatusCode());
    }

    public function testMissingHeaderDefaultsToWildcard(): void
    {
        $params = [];
        $context = new DispatchContext(
            new ServerRequest('GET', '/'),
            $this->factory,
        );

        self::assertTrue($this->routine->when($context, $params));
    }

    public function testQualityFactorOrdering(): void
    {
        $params = [];
        $context = $this->newContext('Accept-Charset', 'iso-8859-1;q=0.5, utf-8;q=0.9');

        self::assertTrue($this->routine->when($context, $params));

        $callback = $this->routine->through($context, $params);
        self::assertNotNull($callback);
        self::assertSame('utf8-content', $callback('ignored'));
    }

    private function newContext(string $header, string $value): DispatchContext
    {
        return new DispatchContext(
            (new ServerRequest('GET', '/'))->withHeader($header, $value),
            $this->factory,
        );
    }
}
