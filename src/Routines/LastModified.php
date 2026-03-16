<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

use DateTime;
use Respect\Rest\Request;

class LastModified extends AbstractRoutine implements ProxyableBy, Unique
{
    public function by(Request $request, $params)
    {
        if (!isset($_SERVER['IF_MODIFIED_SINCE'])) {
            return true;
        }

        $ifModifiedSince = new DateTime($_SERVER['IF_MODIFIED_SINCE']);
        $lastModifiedOn = call_user_func($this->callback, $params);

        header('Last-Modified: '.$lastModifiedOn->format(DateTime::RFC2822));
        if ($lastModifiedOn <= $ifModifiedSince) {
            header('HTTP/1.1 304 Not Modified');

            return false;
        }
    }
}
