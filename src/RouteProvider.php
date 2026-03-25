<?php

declare(strict_types=1);

namespace Respect\Rest;

use Respect\Rest\Routes\AbstractRoute;

interface RouteProvider
{
    /** @return array<int, AbstractRoute> */
    public function getRoutes(): array;

    /** @return array<int, AbstractRoute> */
    public function getHandlers(): array;

    public function getBasePath(): string;
}
