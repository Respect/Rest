<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Http\Message\StreamInterface;
use RuntimeException;
use Throwable;

use function assert;
use function fclose;
use function feof;
use function fread;
use function fseek;
use function fstat;
use function ftell;
use function is_resource;
use function max;
use function rewind;
use function stream_get_contents;
use function stream_get_meta_data;

use const SEEK_SET;

/**
 * Minimal PSR-7 StreamInterface wrapper for PHP stream resources.
 */
final class Stream implements StreamInterface
{
    /** @var resource|closed-resource|null */
    private $resource; // phpcs:ignore SlevomatCodingStandard.Classes.RequireConstructorPropertyPromotion

    /** @param resource $resource */
    public function __construct($resource)
    {
        if (!is_resource($resource)) {
            throw new RuntimeException('Expected a valid PHP resource');
        }

        $this->resource = $resource;
    }

    public function close(): void
    {
        if ($this->resource === null) {
            return;
        }

        fclose($this->resource);
        $this->resource = null;
    }

    /** @return resource|null */
    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;

        return $resource;
    }

    public function getSize(): int|null
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);

        return $stats['size'] ?? null;
    }

    public function tell(): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached');
        }

        $position = ftell($this->resource);
        if ($position === false) {
            throw new RuntimeException('Unable to determine stream position');
        }

        return $position;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        if ($this->resource === null) {
            return false;
        }

        $meta = stream_get_meta_data($this->resource);

        return $meta['seekable'];
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (!$this->isSeekable()) {
            throw new RuntimeException('Stream is not seekable');
        }

        assert($this->resource !== null);
        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new RuntimeException('Unable to seek stream');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return false;
    }

    public function write(string $string): int
    {
        throw new RuntimeException('Stream is not writable');
    }

    public function isReadable(): bool
    {
        return $this->resource !== null;
    }

    public function read(int $length): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached');
        }

        $data = fread($this->resource, max(1, $length));
        if ($data === false) {
            throw new RuntimeException('Unable to read from stream');
        }

        return $data;
    }

    public function getContents(): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached');
        }

        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    public function getMetadata(string|null $key = null): mixed
    {
        if ($this->resource === null) {
            return $key === null ? [] : null;
        }

        $meta = stream_get_meta_data($this->resource);
        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }

    public function __toString(): string
    {
        if ($this->resource === null) {
            return '';
        }

        try {
            rewind($this->resource);

            return stream_get_contents($this->resource) ?: '';
        } catch (Throwable) {
            return '';
        }
    }
}
