<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Generic routine executed after the route */
class Through extends AbstractSyncedRoutine implements ProxyableThrough
{
    public function through(Request $request, $params)
    {
        return $this->execute($request, $params);
    }
}
