<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

class HeadTest implements Routable
{
    public function __construct(private readonly string $expectedHeader)
    {
    }

    public function get(): string
    {
        return 'ok';
    }

    public function getExpectedHeader(): string
    {
        return $this->expectedHeader;
    }
}
