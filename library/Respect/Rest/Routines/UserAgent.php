<?php

namespace Respect\Rest\Routines;

/** Handles User Agent filters */
class UserAgent extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_USER_AGENT';

    protected function compareItens($requested, $provided)
    {
        if ($provided === '*' || preg_match("#$provided#", $requested))
            return true;

        return false;
    }

}
