<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Closure;
use Psr\Http\Message\ResponseInterface;
use ReflectionFunction;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routes\AbstractRoute;

use function is_callable;
use function preg_match;

/** Handles User Agent filters */
final class UserAgent extends AbstractCallbackMediator implements ProxyableBy, ProxyableThrough, Unique
{
    public const string ACCEPT_HEADER = 'HTTP_USER_AGENT';
    private const string THROUGH_ATTRIBUTE = 'userAgentThrough';
    private const string THROUGH_UNSET = '__userAgentThroughUnset';

    /** @param array<int, mixed> $params */
    public function by(DispatchContext $context, array $params): mixed
    {
        $callback = $this->getNegotiatedCallback($context);
        if ($callback === null || !$this->canRunBeforeRoute($callback)) {
            return null;
        }

        $result = $callback();

        if (
            $result === false
            || $result instanceof AbstractRoute
            || $result instanceof ResponseInterface
        ) {
            return $result;
        }

        $context->request = $context->request->withAttribute(
            self::THROUGH_ATTRIBUTE,
            $this->normalizeThroughResult($result),
        );

        return null;
    }

    /** @param array<int, mixed> $params */
    public function through(DispatchContext $context, array $params): mixed
    {
        $preparedResult = $context->request->getAttribute(
            self::THROUGH_ATTRIBUTE,
            self::THROUGH_UNSET,
        );
        if ($preparedResult !== self::THROUGH_UNSET) {
            return $preparedResult ?: null;
        }

        return $this->getNegotiatedCallback($context);
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return array<int, string>
     */
    protected function identifyRequested(DispatchContext $context, array $params): array
    {
        $userAgent = $context->request->getHeaderLine('User-Agent');

        return [$userAgent];
    }

    /** @return array<int, string> */
    protected function considerProvisions(string $requested): array
    {
        return $this->getKeys();
    }

    /** @param array<int, mixed> $params */
    protected function notifyApproved(
        string $requested,
        string $provided,
        DispatchContext $context,
        array $params,
    ): void {
        $this->rememberNegotiatedCallback($context, $this->getCallback($provided));
    }

    /** @param array<int, mixed> $params */
    protected function notifyDeclined(
        string $requested,
        string $provided,
        DispatchContext $context,
        array $params,
    ): void {
        $this->forgetNegotiatedCallback();
    }

    protected function authorize(string $requested, string $provided): mixed
    {
        return $provided === '*' || preg_match('#' . $provided . '#', $requested);
    }

    private function canRunBeforeRoute(callable $callback): bool
    {
        $reflection = new ReflectionFunction(Closure::fromCallable($callback));

        return $reflection->getNumberOfRequiredParameters() === 0;
    }

    private function normalizeThroughResult(mixed $result): callable|false
    {
        if ($result === null || $result === true) {
            return false;
        }

        if (is_callable($result)) {
            return $result;
        }

        return static fn(): mixed => $result;
    }
}
