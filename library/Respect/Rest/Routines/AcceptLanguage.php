<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Handles Language content negotiation */
    class AcceptLanguage extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_ACCEPT_LANGUAGE';

    protected function compareItens($requested, $provided)
    {
        $requested = preg_replace('/^x\-/', '', $requested);
        $provided = preg_replace('/^x\-/', '', $provided);

        if ($requested == $provided)
            return true;

        if (stripos($requested, '-') || !stripos($provided, '-'))
            return false;

        list($providedA, ) = explode('-', $provided);

        if ($requested === $providedA)
            return true;

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
