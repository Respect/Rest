<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

use function implode;

final class MagicMethodController implements Routable
{
    /** @param array<int, mixed> $args */
    public function __call(string $name, array $args): string
    {
        return $name . ':' . implode(',', $args);
    }
}
