<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Generic routine executed before route matching */
class When extends AbstractSyncedRoutine implements ProxyableWhen
{
    public function when(Request $request, $params)
    {
        $valid = $this->execute($request, $params);

        if (!$valid) {
            header('HTTP/1.1 400');
        }

        return $valid;
    }
}
