<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

/** Handles mime type content negotiation */
class Accept extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_ACCEPT';

    protected function authorize($requested, $provided)
    {
        if ($requested === $provided || $requested === '*/*') {
            return $provided;
        }

        if (false !== strpos($requested, '/')) {
            list($requestedA, $requestedB) = explode('/', $requested);
            list($providedA,) = explode('/', $provided);

            if ($providedA === $requestedA && $requestedB === '*') {
                return $providedA;
            }
        }

        return parent::authorize($requested, $provided);
    }
}
