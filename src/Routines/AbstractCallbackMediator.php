<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\DispatchContext;

use function is_callable;
use function spl_object_id;

/**
 * Mediates the callback selection process when choosing the appropriate
 * callback based on the request. Decisions are delegated to the implementation
 * classes.
 */
// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousAbstractClassNaming.SuperfluousPrefix
abstract class AbstractCallbackMediator extends CallbackList implements ProxyableWhen
{
    /** @param array<int, mixed> $params */
    public function when(DispatchContext $context, array $params): mixed
    {
        $requested = '';
        $provided = '';
        $decision = $this->mediate($requested, $provided, $context, $params);

        if ($decision) {
            $this->notifyApproved($requested, $provided, $context, $params);
        } else {
            $this->notifyDeclined($requested, $provided, $context, $params);
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
    abstract protected function identifyRequested(DispatchContext $context, array $params): array;

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
        DispatchContext $context,
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
        DispatchContext $context,
        array $params,
    ): void;

    protected function getNegotiatedCallback(DispatchContext $context): callable|null
    {
        $value = $context->request->getAttribute($this->negotiatedAttributeKey());

        return is_callable($value) ? $value : null;
    }

    protected function rememberNegotiatedCallback(DispatchContext $context, callable $callback): void
    {
        $context->withRequest($context->request->withAttribute(
            $this->negotiatedAttributeKey(),
            $callback,
        ));
    }

    protected function forgetNegotiatedCallback(DispatchContext $context): void
    {
        $context->withRequest($context->request->withAttribute(
            $this->negotiatedAttributeKey(),
            false,
        ));
    }

    protected function authorize(string $requested, string $provided): mixed
    {
        return $requested == $provided;
    }

    private function negotiatedAttributeKey(): string
    {
        return 'rest.negotiated.' . spl_object_id($this);
    }

    /** @param array<int, mixed> $params */
    private function mediate(string &$requested, string &$provided, DispatchContext $context, array $params): bool
    {
        foreach ($this->identifyRequested($context, $params) as $requested) {
            foreach ($this->considerProvisions($requested) as $provided) {
                if ($this->authorize($requested, $provided)) {
                    return true;
                }
            }
        }

        return false;
    }
}
