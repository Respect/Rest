<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;
use SplObjectStorage;

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

    /** @var SplObjectStorage<Request, callable>|false|null */
    protected SplObjectStorage|false|null $negotiated = null;

    /** @param array<int, mixed> $params */
    public function by(Request $request, array $params): mixed
    {
        if (!$this->negotiated instanceof SplObjectStorage || !$this->negotiated->offsetExists($request)) {
            return null;
        }

        $payload = ($this->negotiated[$request])($this->extractInput($request));
        $serverRequest = $request->serverRequest->withAttribute(self::ATTRIBUTE, $payload);
        if (is_array($payload) || is_object($payload) || $payload === null) {
            $serverRequest = $serverRequest->withParsedBody($payload);
        }

        $request->serverRequest = $serverRequest;

        return null;
    }

    /** @param array<int, mixed> $params */
    public function when(Request $request, array $params): mixed
    {
        if ($request->serverRequest->getHeaderLine('Content-Type') === '') {
            return true;
        }

        return parent::when($request, $params);
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return array<int, string>
     */
    protected function identifyRequested(Request $request, array $params): array
    {
        $contentType = $request->serverRequest->getHeaderLine('Content-Type');

        return $contentType !== '' ? [trim(explode(';', $contentType, 2)[0])] : [];
    }

    /** @return array<int, string> */
    protected function considerProvisions(string $requested): array
    {
        return $this->getKeys();
    }

    /** @param array<int, mixed> $params */
    protected function notifyApproved(string $requested, string $provided, Request $request, array $params): void
    {
        /** @var SplObjectStorage<Request, callable> $storage */
        $storage = new SplObjectStorage();
        $this->negotiated = $storage;
        $this->negotiated[$request] = $this->getCallback($provided);
    }

    /** @param array<int, mixed> $params */
    protected function notifyDeclined(string $requested, string $provided, Request $request, array $params): void
    {
        $this->negotiated = false;
        $request->prepareResponse(415);
    }

    protected function extractInput(Request $request): mixed
    {
        $body = (string) $request->serverRequest->getBody();
        if ($body !== '') {
            return $body;
        }

        return $request->serverRequest->getParsedBody();
    }
}
