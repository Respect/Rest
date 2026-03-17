<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

class RouteKnowsGet implements Routable
{
    public function get($param)
    {
        return "ok: $param";
    }
}
