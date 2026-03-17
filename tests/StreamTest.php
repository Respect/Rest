<?php

declare(strict_types=1);

namespace Respect\Rest\Test;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use Respect\Rest\Stream;
use RuntimeException;

use function fopen;
use function fread;
use function fwrite;
use function rewind;

final class StreamTest extends TestCase
{
    #[Test]
    public function implementsStreamInterface(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        self::assertInstanceOf(StreamInterface::class, $stream);
    }

    #[Test]
    public function constructorRejectsNonResource(): void
    {
        self::expectException(RuntimeException::class);
        /** @phpstan-ignore-next-line intentionally passing invalid type */
        new Stream('not a resource');
    }

    #[Test]
    public function toStringReturnsContents(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, 'hello world');
        $stream = new Stream($resource);

        self::assertEquals('hello world', (string) $stream);
    }

    #[Test]
    public function toStringReturnsEmptyAfterDetach(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, 'data');
        $stream = new Stream($resource);
        $stream->detach();

        self::assertEquals('', (string) $stream);
    }

    #[Test]
    public function closeReleasesResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $stream->close();

        self::assertFalse($stream->isReadable());
        self::assertFalse($stream->isSeekable());
    }

    #[Test]
    public function detachReturnsResourceAndNullsInternal(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $detached = $stream->detach();

        self::assertSame($resource, $detached);
        self::assertNull($stream->detach());
    }

    #[Test]
    public function getSizeReturnsStreamSize(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, 'abcdef');
        $stream = new Stream($resource);

        self::assertEquals(6, $stream->getSize());
    }

    #[Test]
    public function getSizeReturnsNullAfterDetach(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $stream->detach();

        self::assertNull($stream->getSize());
    }

    #[Test]
    public function tellReportsPosition(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, 'abcdef');
        rewind($resource);
        fread($resource, 3);
        $stream = new Stream($resource);

        self::assertEquals(3, $stream->tell());
    }

    #[Test]
    public function tellThrowsAfterDetach(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $stream->detach();

        self::expectException(RuntimeException::class);
        $stream->tell();
    }

    #[Test]
    public function eofReturnsTrueAtEnd(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, 'ab');
        rewind($resource);
        fread($resource, 10);
        $stream = new Stream($resource);

        self::assertTrue($stream->eof());
    }

    #[Test]
    public function eofReturnsTrueAfterDetach(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $stream->detach();

        self::assertTrue($stream->eof());
    }

    #[Test]
    public function isSeekableForTempStream(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);

        self::assertTrue($stream->isSeekable());
    }

    #[Test]
    public function seekAndRewind(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, 'abcdef');
        $stream = new Stream($resource);

        $stream->seek(3);
        self::assertEquals(3, $stream->tell());

        $stream->rewind();
        self::assertEquals(0, $stream->tell());
    }

    #[Test]
    public function seekThrowsOnNonSeekableAfterDetach(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $stream->detach();

        self::expectException(RuntimeException::class);
        $stream->seek(0);
    }

    #[Test]
    public function streamIsNotWritable(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);

        self::assertFalse($stream->isWritable());
    }

    #[Test]
    public function writeThrows(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);

        self::expectException(RuntimeException::class);
        $stream->write('data');
    }

    #[Test]
    public function streamIsReadable(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);

        self::assertTrue($stream->isReadable());
    }

    #[Test]
    public function readReturnsData(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, 'hello');
        rewind($resource);
        $stream = new Stream($resource);

        self::assertEquals('hel', $stream->read(3));
        self::assertEquals('lo', $stream->read(10));
    }

    #[Test]
    public function readThrowsAfterDetach(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $stream->detach();

        self::expectException(RuntimeException::class);
        $stream->read(1);
    }

    #[Test]
    public function getContentsFromCurrentPosition(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        fwrite($resource, 'abcdef');
        rewind($resource);
        fread($resource, 2);
        $stream = new Stream($resource);

        self::assertEquals('cdef', $stream->getContents());
    }

    #[Test]
    public function getContentsThrowsAfterDetach(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $stream->detach();

        self::expectException(RuntimeException::class);
        $stream->getContents();
    }

    #[Test]
    public function getMetadataReturnsArray(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $meta = $stream->getMetadata();

        self::assertIsArray($meta);
        self::assertArrayHasKey('uri', $meta);
    }

    #[Test]
    public function getMetadataByKey(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);

        self::assertEquals('php://temp', $stream->getMetadata('uri'));
        self::assertNull($stream->getMetadata('nonexistent'));
    }

    #[Test]
    public function getMetadataAfterDetach(): void
    {
        $resource = fopen('php://temp', 'r+');
        self::assertIsResource($resource);
        $stream = new Stream($resource);
        $stream->detach();

        self::assertEquals([], $stream->getMetadata());
        self::assertNull($stream->getMetadata('uri'));
    }
}
