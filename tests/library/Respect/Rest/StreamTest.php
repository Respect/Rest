<?php

namespace Respect\Rest;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

class StreamTest extends TestCase
{
    #[Test]
    public function implementsStreamInterface(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $this->assertInstanceOf(StreamInterface::class, $stream);
    }

    #[Test]
    public function constructorRejectsNonResource(): void
    {
        $this->expectException(RuntimeException::class);
        new Stream('not a resource');
    }

    #[Test]
    public function toStringReturnsContents(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'hello world');
        $stream = new Stream($resource);

        $this->assertEquals('hello world', (string) $stream);
    }

    #[Test]
    public function toStringReturnsEmptyAfterDetach(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'data');
        $stream = new Stream($resource);
        $stream->detach();

        $this->assertEquals('', (string) $stream);
    }

    #[Test]
    public function closeReleasesResource(): void
    {
        $resource = fopen('php://temp', 'r+');
        $stream = new Stream($resource);
        $stream->close();

        $this->assertFalse($stream->isReadable());
        $this->assertFalse($stream->isSeekable());
    }

    #[Test]
    public function detachReturnsResourceAndNullsInternal(): void
    {
        $resource = fopen('php://temp', 'r+');
        $stream = new Stream($resource);
        $detached = $stream->detach();

        $this->assertSame($resource, $detached);
        $this->assertNull($stream->detach());
    }

    #[Test]
    public function getSizeReturnsStreamSize(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'abcdef');
        $stream = new Stream($resource);

        $this->assertEquals(6, $stream->getSize());
    }

    #[Test]
    public function getSizeReturnsNullAfterDetach(): void
    {
        $resource = fopen('php://temp', 'r+');
        $stream = new Stream($resource);
        $stream->detach();

        $this->assertNull($stream->getSize());
    }

    #[Test]
    public function tellReportsPosition(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'abcdef');
        rewind($resource);
        fread($resource, 3);
        $stream = new Stream($resource);

        $this->assertEquals(3, $stream->tell());
    }

    #[Test]
    public function tellThrowsAfterDetach(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->detach();

        $this->expectException(RuntimeException::class);
        $stream->tell();
    }

    #[Test]
    public function eofReturnsTrueAtEnd(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'ab');
        rewind($resource);
        fread($resource, 10);
        $stream = new Stream($resource);

        $this->assertTrue($stream->eof());
    }

    #[Test]
    public function eofReturnsTrueAfterDetach(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->detach();

        $this->assertTrue($stream->eof());
    }

    #[Test]
    public function isSeekableForTempStream(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));

        $this->assertTrue($stream->isSeekable());
    }

    #[Test]
    public function seekAndRewind(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'abcdef');
        $stream = new Stream($resource);

        $stream->seek(3);
        $this->assertEquals(3, $stream->tell());

        $stream->rewind();
        $this->assertEquals(0, $stream->tell());
    }

    #[Test]
    public function seekThrowsOnNonSeekableAfterDetach(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->detach();

        $this->expectException(RuntimeException::class);
        $stream->seek(0);
    }

    #[Test]
    public function streamIsNotWritable(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));

        $this->assertFalse($stream->isWritable());
    }

    #[Test]
    public function writeThrows(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));

        $this->expectException(RuntimeException::class);
        $stream->write('data');
    }

    #[Test]
    public function streamIsReadable(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));

        $this->assertTrue($stream->isReadable());
    }

    #[Test]
    public function readReturnsData(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'hello');
        rewind($resource);
        $stream = new Stream($resource);

        $this->assertEquals('hel', $stream->read(3));
        $this->assertEquals('lo', $stream->read(10));
    }

    #[Test]
    public function readThrowsAfterDetach(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->detach();

        $this->expectException(RuntimeException::class);
        $stream->read(1);
    }

    #[Test]
    public function getContentsFromCurrentPosition(): void
    {
        $resource = fopen('php://temp', 'r+');
        fwrite($resource, 'abcdef');
        rewind($resource);
        fread($resource, 2);
        $stream = new Stream($resource);

        $this->assertEquals('cdef', $stream->getContents());
    }

    #[Test]
    public function getContentsThrowsAfterDetach(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->detach();

        $this->expectException(RuntimeException::class);
        $stream->getContents();
    }

    #[Test]
    public function getMetadataReturnsArray(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $meta = $stream->getMetadata();

        $this->assertIsArray($meta);
        $this->assertArrayHasKey('uri', $meta);
    }

    #[Test]
    public function getMetadataByKey(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));

        $this->assertEquals('php://temp', $stream->getMetadata('uri'));
        $this->assertNull($stream->getMetadata('nonexistent'));
    }

    #[Test]
    public function getMetadataAfterDetach(): void
    {
        $stream = new Stream(fopen('php://temp', 'r+'));
        $stream->detach();

        $this->assertEquals([], $stream->getMetadata());
        $this->assertNull($stream->getMetadata('uri'));
    }
}
