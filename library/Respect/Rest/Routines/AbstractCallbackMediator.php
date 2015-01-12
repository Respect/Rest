<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

use Respect\Rest\Request;
use UnexpectedValueException;

/**
 * Mediates the callback selection process when choosing the appropriate
 * callback based on the request. Decisions are deligated to the implementation
 * classes.
 * @author Nick Lombard <github@jigsoft.co.za>
 */
abstract class AbstractCallbackMediator extends AbstractCallbackList implements ProxyableWhen
{
    /** Implementations are tasked to provide a list of appropriate request identifiers */
    abstract protected function identifyRequested(Request $request, $params);
    /** Based on each of the identified particulars a list of provisiens must be supplied */
    abstract protected function considerProvisions($requested);
    /** If an agreement wasreached to allow positive notification and preparation */
    abstract protected function notifyApproved($requested, $provided, Request $request, $params);
    /** If declined to apply the nescessary notifications and preparations */
    abstract protected function notifyDeclined($requested, $provided, Request $request, $params);

    /**
     * Mediate the authorization or ultimately service denial based on client's
     * request and implementation's provisioning.
     * The outcome will trigger the appropriate notification mehtods to allow
     * for appropriate header configuration if nescesary.
     */
    public function when(Request $request, $params)
    {
        if (true ==
        ($decision = $this->mediate($requested, $provided, $request, $params))) {
            $this->notifyApproved($requested, $provided, $request, $params);
        } else {
            $this->notifyDeclined($requested, $provided, $request, $params);
        }

        return $decision;
    }

    /**
     * Implementing classes will be asked to identify the request list and for
     * each item in the list of requests identified a list of provisions must be
     * supplied.
     * The implementation gets to authorize against each possibility or the
     * request will be declined if no satisfactory requirements are reached.
     **/
    private function mediate(&$requested, &$provided, Request $request, $params)
    {
        if (is_array($requests = $this->identifyRequested($request, $params))) {
            foreach ($requests as $requested) {
                if (is_array($provisions = $this->considerProvisions($requested))) {
                    foreach ($provisions as $provided) {
                        if ($this->authorize($requested, $provided, $request)) {
                            return true;
                        }
                    }
                } else {
                    throw new UnexpectedValueException('Provisions must be an array of 0 to many.');
                }
            }
        } else {
            throw new UnexpectedValueException('Requests must be an array of 0 to many.');
        }

        return false;
    }

    /** Affirm apprval or decline the r */
    protected function authorize($requested, $provided)
    {
        return $requested == $provided;
    }
}
