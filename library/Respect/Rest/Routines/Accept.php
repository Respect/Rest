<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Handles mime type content negotiation */
class Accept extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_ACCEPT';

    protected function compareItems($requested, $provided)
    {
        if ($requested === $provided || $requested === '*/*')
                return $provided;

        list($requestedA, $requestedB) = explode('/', $requested);
        list($providedA, ) = explode('/', $provided);

        if ($providedA === $requestedA && $requestedB === '*')
                return $providedA;

        return false;
    }

    public function when(Request $request, $params)
    {
        $valid = parent::when($request, $params);

        if (!$valid)
            header('HTTP/1.1 406');

        return $valid;
    }
}
