<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\DispatchContext;

use function explode;
use function is_array;
use function is_object;
use function trim;

/** Handles content type content negotiation */
final class ContentType extends AbstractCallbackMediator implements ProxyableBy, Unique
{
    public const string ATTRIBUTE = 'contentType';

    /** @var array<string, callable> */
    protected array $contentMap = [];

    /** @param array<int, mixed> $params */
    public function by(DispatchContext $context, array $params): mixed
    {
        $callback = $this->getNegotiatedCallback($context);
        if ($callback === null) {
            return null;
        }

        $payload = $callback($this->extractInput($context));
        $psrRequest = $context->request->withAttribute(self::ATTRIBUTE, $payload);
        if (is_array($payload) || is_object($payload) || $payload === null) {
            $psrRequest = $psrRequest->withParsedBody($payload);
        }

        $context->request = $psrRequest;

        return null;
    }

    /** @param array<int, mixed> $params */
    public function when(DispatchContext $context, array $params): mixed
    {
        if ($context->request->getHeaderLine('Content-Type') === '') {
            return true;
        }

        return parent::when($context, $params);
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return array<int, string>
     */
    protected function identifyRequested(DispatchContext $context, array $params): array
    {
        $contentType = $context->request->getHeaderLine('Content-Type');

        return $contentType !== '' ? [trim(explode(';', $contentType, 2)[0])] : [];
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
        $context->prepareResponse(415);
    }

    protected function extractInput(DispatchContext $context): mixed
    {
        $body = (string) $context->request->getBody();
        if ($body !== '') {
            return $body;
        }

        return $context->request->getParsedBody();
    }
}
