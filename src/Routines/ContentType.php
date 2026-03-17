<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;
use SplObjectStorage;

/** Handles content type content negotiation */
final class ContentType extends AbstractCallbackMediator implements ProxyableBy, Unique
{
    /** @var array<string, callable> */
    protected array $contentMap = [];

    /** @var SplObjectStorage<Request, callable>|false|null */
    protected SplObjectStorage|false|null $negotiated = null;

    /** @param array<int, mixed> $params */
    public function by(Request $request, array $params): mixed
    {
        if ($this->negotiated instanceof SplObjectStorage && $this->negotiated->contains($request)) {
            return ($this->negotiated[$request])();
        }

        return null;
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return array<int, string>
     */
    protected function identifyRequested(Request $request, array $params): array
    {
        $contentType = $request->serverRequest->getHeaderLine('Content-Type');

        return $contentType !== '' ? [$contentType] : [];
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
    }
}
