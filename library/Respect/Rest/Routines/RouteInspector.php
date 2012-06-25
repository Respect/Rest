<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Routes\AbstractRoute;

interface RouteInspector
{
    public function inspect(array $routes, AbstractRoute $active, $allowedMethods, $method, $uri);
}

