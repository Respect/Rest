<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Handles charset content negotiation*/
class AcceptCharset extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_ACCEPT_CHARSET';

}
