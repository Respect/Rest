<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final readonly class HttpFactories
{
    public function __construct(
        public ResponseFactoryInterface $responses,
        public StreamFactoryInterface $streams,
    ) {
    }
}
