<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

/** Handles charset content negotiation */
class AcceptCharset extends AbstractAccept
{
    const string ACCEPT_HEADER = 'HTTP_ACCEPT_CHARSET';
}
