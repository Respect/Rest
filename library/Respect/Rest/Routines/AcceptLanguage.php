<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Handles Language content negotiation */
class AcceptLanguage extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_ACCEPT_LANGUAGE';

    protected function authorize($requested, $provided)
    { 
        $requested = preg_replace('/^x\-/', '', $requested);
        $provided = preg_replace('/^x\-/', '', $provided);

        if ($requested == $provided)
            return $provided;

        if (stripos($requested, '-') || !stripos($provided, '-'))
            return false;

        list($providedA, ) = explode('-', $provided);

        if ($requested === $providedA)
            return $providedA;

        return parent::authorize($requested, $provided);
    }

}
