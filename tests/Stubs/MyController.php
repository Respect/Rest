<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

class MyController implements Routable
{
    /** @var array<int|string, mixed> */
    protected array $params = [];

    public function __construct(mixed ...$args)
    {
        $this->params = $args;
    }

    /** @return array<int, mixed> */
    public function get(mixed $user): array
    {
        return [$user, 'get', $this->params];
    }

    /** @return array<int, mixed> */
    public function post(mixed $user): array
    {
        return [$user, 'post', $this->params];
    }
}
