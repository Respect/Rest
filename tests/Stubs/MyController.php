<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Respect\Rest\Routable;

class MyController implements Routable
{
    protected $params = [];

    public function __construct()
    {
        $this->params = func_get_args();
        return 'whoops';
    }

    public function get($user)
    {
        return [$user, 'get', $this->params];
    }

    public function post($user)
    {
        return [$user, 'post', $this->params];
    }
}
