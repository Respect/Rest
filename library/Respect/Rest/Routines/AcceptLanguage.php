<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

/** Handles Language content negotiation */
class AcceptLanguage extends AbstractAccept
{
    const ACCEPT_HEADER = 'HTTP_ACCEPT_LANGUAGE';

    protected function authorize($requested, $provided)
    {
        $requested = preg_replace('/^x\-/', '', $requested);
        $provided = preg_replace('/^x\-/', '', $provided);

        if ($requested == $provided) {
            return $provided;
        }

        if (stripos($requested, '-') || !stripos($provided, '-')) {
            return false;
        }

        list($providedA,) = explode('-', $provided);

        if ($requested === $providedA) {
            return $providedA;
        }

        return parent::authorize($requested, $provided);
    }
}
