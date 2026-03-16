<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/** Routine that runs before the route */
interface ProxyableBy
{
    /** Executed before the route */
    public function by(Request $request, $params);
}
