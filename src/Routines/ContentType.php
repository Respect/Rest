<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use SplObjectStorage;
use Respect\Rest\Request;

/** Handles content type content negotiation */
final class ContentType extends AbstractCallbackMediator implements ProxyableBy, Unique
{
    protected array $contentMap = [];
    protected SplObjectStorage|false|null $negotiated = null;

    protected function identifyRequested(Request $request, array $params): array
    {
        $contentType = $request->serverRequest->getHeaderLine('Content-Type');

        return $contentType !== '' ? [$contentType] : [];
    }

    protected function considerProvisions(string $requested): array
    {
        return $this->getKeys();
    }

    protected function notifyApproved(string $requested, string $provided, Request $request, array $params): void
    {
        $this->negotiated = new SplObjectStorage();
        $this->negotiated[$request] = $this->getCallback($provided);
    }

    protected function notifyDeclined(string $requested, string $provided, Request $request, array $params): void
    {
        $this->negotiated = false;
    }

    public function by(Request $request, array $params): mixed
    {
        if (false !== $this->negotiated) {
            return ($this->negotiated[$request])();
        }

        return null;
    }
}
