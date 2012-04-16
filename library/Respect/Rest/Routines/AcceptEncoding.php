<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Handles encoding content negotiation */
class AcceptEncoding extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_ACCEPT_ENCODING';

    public function when(Request $request, $params)
    {
        $valid = parent::when($request, $params);

        if (!$valid)
            header('HTTP/1.1 406');

        return $valid;
    }
}

