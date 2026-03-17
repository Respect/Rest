<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

/**
 * Mediates the callback selection process when choosing the appropriate
 * callback based on the request. Decisions are delegated to the implementation
 * classes.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousAbstractClassNaming.SuperfluousPrefix
abstract class AbstractCallbackMediator extends CallbackList implements ProxyableWhen
{
    /** @param array<int, mixed> $params */
    public function when(Request $request, array $params): mixed
    {
        $requested = '';
        $provided = '';
        $decision = $this->mediate($requested, $provided, $request, $params);

        if ($decision) {
            $this->notifyApproved($requested, $provided, $request, $params);
        } else {
            $this->notifyDeclined($requested, $provided, $request, $params);
        }

        return $decision;
    }

    /**
     * Implementations are tasked to provide a list of appropriate request identifiers
     *
     * @param array<int, mixed> $params
     *
     * @return array<int, string>
     */
    abstract protected function identifyRequested(Request $request, array $params): array;

    /**
     * Based on each of the identified particulars a list of provisions must be supplied
     *
     * @return array<int, string>
     */
    abstract protected function considerProvisions(string $requested): array;

    /**
     * If an agreement was reached to allow positive notification and preparation
     *
     * @param array<int, mixed> $params
     */
    abstract protected function notifyApproved(
        string $requested,
        string $provided,
        Request $request,
        array $params,
    ): void;

    /**
     * If declined to apply the necessary notifications and preparations
     *
     * @param array<int, mixed> $params
     */
    abstract protected function notifyDeclined(
        string $requested,
        string $provided,
        Request $request,
        array $params,
    ): void;

    protected function authorize(string $requested, string $provided): mixed
    {
        return $requested == $provided;
    }

    /** @param array<int, mixed> $params */
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
}
