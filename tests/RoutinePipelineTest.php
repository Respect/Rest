<?php

declare(strict_types=1);

namespace Respect\Rest\Test;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Respect\Rest\DispatchContext;
use Respect\Rest\HttpFactories;
use Respect\Rest\Routes\Callback;
use Respect\Rest\RoutinePipeline;
use Respect\Rest\Routines\By;
use Respect\Rest\Routines\Through;
use Respect\Rest\Routines\When;

/** @covers Respect\Rest\RoutinePipeline */
final class RoutinePipelineTest extends TestCase
{
    private HttpFactories $httpFactories;

    private RoutinePipeline $pipeline;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->httpFactories = new HttpFactories($factory, $factory);
        $this->pipeline = new RoutinePipeline();
    }

    public function testMatchesReturnsTrueWithNoWhenRoutines(): void
    {
        $route = new Callback('GET', '/test', static fn(): string => 'ok');
        $context = $this->newContext();
        $context->route = $route;
        $params = [];

        self::assertTrue($this->pipeline->matches($context, $route, $params));
    }

    public function testMatchesReturnsFalseWhenWhenRoutineReturnsFalse(): void
    {
        $route = new Callback('GET', '/test', static fn(): string => 'ok');
        $route->appendRoutine(new When(static fn(): bool => false));
        $context = $this->newContext();
        $context->route = $route;
        $params = [];

        self::assertFalse($this->pipeline->matches($context, $route, $params));
    }

    public function testMatchesReturnsTrueWhenWhenRoutineReturnsTrue(): void
    {
        $route = new Callback('GET', '/test', static fn(): string => 'ok');
        $route->appendRoutine(new When(static fn(): bool => true));
        $context = $this->newContext();
        $context->route = $route;
        $params = [];

        self::assertTrue($this->pipeline->matches($context, $route, $params));
    }

    public function testProcessByReturnsNullWithNoByRoutines(): void
    {
        $route = new Callback('GET', '/test', static fn(): string => 'ok');
        $context = $this->newContext();
        $context->route = $route;

        self::assertNull($this->pipeline->processBy($context, $route));
    }

    public function testProcessByReturnsResponseWhenByReturnsResponse(): void
    {
        $route = new Callback('GET', '/test', static fn(): string => 'ok');
        $response = $this->httpFactories->responses->createResponse(401);
        $route->appendRoutine(new By(static fn() => $response));
        $context = $this->newContext();
        $context->route = $route;

        $result = $this->pipeline->processBy($context, $route);

        self::assertInstanceOf(ResponseInterface::class, $result);
        self::assertSame(401, $result->getStatusCode());
    }

    public function testProcessByReturnsFalseWhenByReturnsFalse(): void
    {
        $route = new Callback('GET', '/test', static fn(): string => 'ok');
        $route->appendRoutine(new By(static fn(): bool => false));
        $context = $this->newContext();
        $context->route = $route;

        self::assertFalse($this->pipeline->processBy($context, $route));
    }

    public function testProcessThroughChainsCallableResults(): void
    {
        $route = new Callback('GET', '/test', static fn(): string => 'ok');
        $route->appendRoutine(new Through(static fn() => static fn(string $v): string => $v . '-A'));
        $route->appendRoutine(new Through(static fn() => static fn(string $v): string => $v . '-B'));
        $context = $this->newContext();
        $context->route = $route;

        $result = $this->pipeline->processThrough($context, $route, 'start');

        self::assertSame('start-A-B', $result);
    }

    public function testProcessThroughSkipsNonCallableResults(): void
    {
        $route = new Callback('GET', '/test', static fn(): string => 'ok');
        $route->appendRoutine(new Through(static fn(): null => null));
        $context = $this->newContext();
        $context->route = $route;

        $result = $this->pipeline->processThrough($context, $route, 'unchanged');

        self::assertSame('unchanged', $result);
    }

    private function newContext(): DispatchContext
    {
        return new DispatchContext(
            new ServerRequest('GET', '/test'),
            $this->httpFactories->responses,
            $this->httpFactories->streams,
        );
    }
}
