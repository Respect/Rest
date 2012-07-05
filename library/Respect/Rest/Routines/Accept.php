<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Handles mime type content negotiation */
class Accept extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_ACCEPT';

    protected function authorize($requested, $provided)
    {
        if ($requested === $provided || $requested === '*/*')
                return $provided;

        if (false !== strpos($requested, '/')) {
            list($requestedA, $requestedB) = explode('/', $requested);
            list($providedA, ) = explode('/', $provided);

            if ($providedA === $requestedA && $requestedB === '*')
                    return $providedA;
        }
        return parent::authorize($requested, $provided);
    }

}
