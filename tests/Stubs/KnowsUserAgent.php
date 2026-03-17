<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use ReflectionMethod;
use Respect\Rest\Routines\UserAgent;

class KnowsUserAgent
{
    private UserAgent $userAgent;

    public function __construct(array $list)
    {
        $this->userAgent = new UserAgent($list);
    }

    public function knowsCompareItems($requested, $provided)
    {
        $ref = new ReflectionMethod($this->userAgent, 'authorize');
        return $ref->invoke($this->userAgent, $requested, $provided);
    }
}
