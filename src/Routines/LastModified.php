<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use DateTime;
use Respect\Rest\Request;

use function assert;

final class LastModified extends AbstractRoutine implements ProxyableBy, Unique
{
    /** @param array<int, mixed> $params */
    public function by(Request $request, array $params): mixed
    {
        $ifModifiedSince = $request->serverRequest->getHeaderLine('If-Modified-Since');

        if ($ifModifiedSince === '') {
            return true;
        }

        $ifModifiedSince = new DateTime($ifModifiedSince);
        $lastModifiedOn = ($this->callback)($params);

        if ($lastModifiedOn <= $ifModifiedSince) {
            assert($request->route?->responseFactory !== null);
            $response = $request->route->responseFactory->createResponse(304);
            $response = $response->withHeader('Last-Modified', $lastModifiedOn->format(DateTime::RFC2822));

            return $response;
        }

        return true;
    }
}
