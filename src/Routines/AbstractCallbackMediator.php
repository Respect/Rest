<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/**
 * Mediates the callback selection process when choosing the appropriate
 * callback based on the request. Decisions are delegated to the implementation
 * classes.
 * @author Nick Lombard <github@jigsoft.co.za>
 */
abstract class AbstractCallbackMediator extends CallbackList implements ProxyableWhen
{
    /** Implementations are tasked to provide a list of appropriate request identifiers */
    abstract protected function identifyRequested(Request $request, array $params): array;
    /** Based on each of the identified particulars a list of provisions must be supplied */
    abstract protected function considerProvisions(string $requested): array;
    /** If an agreement was reached to allow positive notification and preparation */
    abstract protected function notifyApproved(string $requested, string $provided, Request $request, array $params): void;
    /** If declined to apply the necessary notifications and preparations */
    abstract protected function notifyDeclined(string $requested, string $provided, Request $request, array $params): void;

    public function when(Request $request, array $params): mixed
    {
        $requested = '';
        $provided = '';

        if (true ==
        ($decision = $this->mediate($requested, $provided, $request, $params))) {
            $this->notifyApproved($requested, $provided, $request, $params);
        } else {
            $this->notifyDeclined($requested, $provided, $request, $params);
        }

        return $decision;
    }

    private function mediate(string &$requested, string &$provided, Request $request, array $params): bool
    {
        foreach ($this->identifyRequested($request, $params) as $requested) {
            foreach ($this->considerProvisions($requested) as $provided) {
                if ($this->authorize($requested, $provided)) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function authorize(string $requested, string $provided): mixed
    {
        return $requested == $provided;
    }
}
