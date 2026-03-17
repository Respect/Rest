<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

/** Handles charset content negotiation */
final class AcceptCharset extends AbstractAccept
{
    public const string ACCEPT_HEADER = 'HTTP_ACCEPT_CHARSET';
}
