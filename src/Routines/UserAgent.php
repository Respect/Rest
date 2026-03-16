<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use SplObjectStorage;
use Respect\Rest\Request;

/** Handles User Agent filters */
final class UserAgent extends AbstractCallbackMediator implements ProxyableThrough, Unique
{
    const string ACCEPT_HEADER = 'HTTP_USER_AGENT';
    private SplObjectStorage|false $negotiated = false;

    protected function identifyRequested(Request $request, array $params): array
    {
        $userAgent = $request->serverRequest->getHeaderLine('User-Agent');

        return [$userAgent];
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

    protected function authorize(string $requested, string $provided): mixed
    {
        if ($provided === '*' || preg_match("#$provided#", $requested)) {
            return true;
        }

        return false;
    }

    public function through(Request $request, array $params): mixed
    {
        if (false !== $this->negotiated) {
            return $this->negotiated[$request];
        }

        return null;
    }
}
