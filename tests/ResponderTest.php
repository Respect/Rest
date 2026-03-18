<?php

declare(strict_types=1);

namespace Respect\Rest\Test;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Responder;

use function fopen;
use function fwrite;
use function rewind;

/** @covers Respect\Rest\Responder */
final class ResponderTest extends TestCase
{
    public function testNormalizeUsesStreamFactoryForResources(): void
    {
        $factory = new Psr17Factory();
        $responder = new Responder($factory, $factory);
        $resource = fopen('php://temp', 'r+');

        self::assertIsResource($resource);
        fwrite($resource, 'resource body');
        rewind($resource);

        $response = $responder->normalize($resource);

        self::assertSame('resource body', (string) $response->getBody());
    }

    public function testNormalizeBuildsJsonResponsesThroughFactories(): void
    {
        $factory = new Psr17Factory();
        $responder = new Responder($factory, $factory);

        $response = $responder->normalize(['user' => 'alice']);

        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('{"user":"alice"}', (string) $response->getBody());
    }

    public function testFinalizeStripsHeadBodiesWithFactoryCreatedEmptyStream(): void
    {
        $factory = new Psr17Factory();
        $responder = new Responder($factory, $factory);

        $response = $responder->finalize('payload', null, [], [], false, 'HEAD');

        self::assertSame('', (string) $response->getBody());
    }
}
