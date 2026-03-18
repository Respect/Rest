<?php

declare(strict_types=1);

namespace Respect\Rest\Test;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;
use Respect\Rest\HttpFactories;

/** @covers Respect\Rest\HttpFactories */
final class HttpFactoriesTest extends TestCase
{
    public function testItStoresSinglePsr17FactoryForResponsesAndStreams(): void
    {
        $factory = new Psr17Factory();
        $factories = new HttpFactories($factory, $factory);

        self::assertSame($factory, $factories->responses);
        self::assertSame($factory, $factories->streams);
    }

    public function testItAcceptsSeparateResponseAndStreamFactories(): void
    {
        $innerFactory = new Psr17Factory();
        $responseFactory = new class ($innerFactory) implements ResponseFactoryInterface {
            public function __construct(private Psr17Factory $innerFactory)
            {
            }

            public function createResponse(
                int $code = 200,
                string $reasonPhrase = '',
            ): ResponseInterface {
                return $this->innerFactory->createResponse($code, $reasonPhrase);
            }
        };
        $streamFactory = new class ($innerFactory) implements StreamFactoryInterface {
            public function __construct(private Psr17Factory $innerFactory)
            {
            }

            public function createStream(string $content = ''): StreamInterface
            {
                return $this->innerFactory->createStream($content);
            }

            public function createStreamFromFile(string $filename, string $mode = 'r'): StreamInterface
            {
                return $this->innerFactory->createStreamFromFile($filename, $mode);
            }

            /** @param resource $resource */
            public function createStreamFromResource($resource): StreamInterface
            {
                return $this->innerFactory->createStreamFromResource($resource);
            }
        };

        $factories = new HttpFactories($responseFactory, $streamFactory);

        self::assertSame($responseFactory, $factories->responses);
        self::assertSame($streamFactory, $factories->streams);
    }
}
