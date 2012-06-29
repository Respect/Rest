<?php

namespace Respect\Rest\Routines;

use DateTime;
use SplObjectStorage;
use Respect\Rest\Request;

class LastModified extends AbstractRoutine implements ProxyableBy, Unique
{

    public function by(Request $request, $params)
    {
        if (!isset($_SERVER['IF_MODIFIED_SINCE']))
            return true;

        $ifModifiedSince = new DateTime($_SERVER['IF_MODIFIED_SINCE']);
        $lastModifiedOn = call_user_func($this->callback, $params);

        header('Last-Modified: '.$lastModifiedOn->format(DateTime::RFC2822));
        if ($lastModifiedOn <= $ifModifiedSince) {
            header('HTTP/1.1 304 Not Modified');
            return false;
        }

    }

}
