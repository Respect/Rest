<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Handles charset content negotiation*/
class AcceptCharset extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_ACCEPT_CHARSET';

    public function when(Request $request, $params)
    {
        $valid = parent::when($request, $params);

        if (!$valid)
            header('HTTP/1.1 406');

        return $valid;
    }
}
