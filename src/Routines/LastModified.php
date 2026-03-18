<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use DateTime;
use Respect\Rest\DispatchContext;

final class LastModified extends AbstractRoutine implements ProxyableBy, Unique
{
    /** @param array<int, mixed> $params */
    public function by(DispatchContext $context, array $params): mixed
    {
        $ifModifiedSince = $context->request->getHeaderLine('If-Modified-Since');

        if ($ifModifiedSince === '') {
            return true;
        }

        $ifModifiedSince = new DateTime($ifModifiedSince);
        $lastModifiedOn = ($this->callback)($params);

        if ($lastModifiedOn <= $ifModifiedSince) {
            $response = $context->responseFactory->createResponse(304);
            $response = $response->withHeader('Last-Modified', $lastModifiedOn->format(DateTime::RFC2822));

            return $response;
        }

        return true;
    }
}
