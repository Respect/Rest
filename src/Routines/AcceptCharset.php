<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

/** Handles charset content negotiation*/
class AcceptCharset extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_ACCEPT_CHARSET';
}
