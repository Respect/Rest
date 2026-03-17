<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;
use SplObjectStorage;

use function preg_match;

/** Handles User Agent filters */
final class UserAgent extends AbstractCallbackMediator implements ProxyableThrough, Unique
{
    public const string ACCEPT_HEADER = 'HTTP_USER_AGENT';

    /** @var SplObjectStorage<Request, callable>|false */
    private SplObjectStorage|false $negotiated = false;

    /** @param array<int, mixed> $params */
    public function through(Request $request, array $params): mixed
    {
        if ($this->negotiated !== false) {
            return $this->negotiated[$request];
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
        $userAgent = $request->serverRequest->getHeaderLine('User-Agent');

        return [$userAgent];
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

    protected function authorize(string $requested, string $provided): mixed
    {
        return $provided === '*' || preg_match('#' . $provided . '#', $requested);
    }
}
