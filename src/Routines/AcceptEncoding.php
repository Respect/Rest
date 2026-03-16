<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

/** Handles encoding content negotiation */
final class AcceptEncoding extends AbstractAccept
{
    const string ACCEPT_HEADER = 'HTTP_ACCEPT_ENCODING';
}
