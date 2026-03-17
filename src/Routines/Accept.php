<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use function explode;
use function strpos;

/** Handles mime type content negotiation */
final class Accept extends AbstractAccept
{
    public const string ACCEPT_HEADER = 'HTTP_ACCEPT';

    protected function authorize(string $requested, string $provided): mixed
    {
        if ($requested === $provided || $requested === '*/*') {
            return $provided;
        }

        if (strpos($requested, '/') !== false) {
            [$requestedA, $requestedB] = explode('/', $requested);
            [$providedA] = explode('/', $provided);

            if ($providedA === $requestedA && $requestedB === '*') {
                return $providedA;
            }
        }

        return parent::authorize($requested, $provided);
    }
}
