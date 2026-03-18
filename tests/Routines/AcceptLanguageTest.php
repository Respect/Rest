<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routines\AcceptLanguage;

/** @covers Respect\Rest\Routines\AcceptLanguage */
final class AcceptLanguageTest extends TestCase
{
    private Psr17Factory $factory;

    private AcceptLanguage $routine;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
        $this->routine = new AcceptLanguage([
            'en' => static fn(): string => 'english',
            'en-US' => static fn(): string => 'american english',
            'pt-BR' => static fn(): string => 'brazilian portuguese',
        ]);
    }

    public function testExactLanguageMatch(): void
    {
        $params = [];
        $context = $this->newContext('Accept-Language', 'en-US');

        self::assertTrue($this->routine->when($context, $params));
    }

    public function testPrefixMatchEnToEnUS(): void
    {
        $routine = new AcceptLanguage([
            'en-US' => static fn(): string => 'american english',
        ]);
        $params = [];
        $context = $this->newContext('Accept-Language', 'en');

        self::assertTrue($routine->when($context, $params));
    }

    public function testNotAcceptableOnMismatch(): void
    {
        $params = [];
        $context = $this->newContext('Accept-Language', 'fr');

        self::assertFalse($this->routine->when($context, $params));
        self::assertTrue($context->hasPreparedResponse());
        self::assertSame(406, $context->response()?->getStatusCode());
    }

    public function testXPrefixIsStripped(): void
    {
        $routine = new AcceptLanguage([
            'klingon' => static fn(): string => 'tlhIngan',
        ]);
        $params = [];
        $context = $this->newContext('Accept-Language', 'x-klingon');

        self::assertTrue($routine->when($context, $params));
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
        $context = $this->newContext('Accept-Language', 'en;q=0.5, pt-BR;q=0.9');

        self::assertTrue($this->routine->when($context, $params));

        $callback = $this->routine->through($context, $params);
        self::assertNotNull($callback);
        self::assertSame('brazilian portuguese', $callback('ignored'));
    }

    private function newContext(string $header, string $value): DispatchContext
    {
        return new DispatchContext(
            (new ServerRequest('GET', '/'))->withHeader($header, $value),
            $this->factory,
        );
    }
}
