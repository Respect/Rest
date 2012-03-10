<?php

namespace Respect\Rest\Routines;

/** Callback Routine that sync params */
interface ParamSynced
{
    /** Returns parameters for the callback*/
    public function getParameters();
}
