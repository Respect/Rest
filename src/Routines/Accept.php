<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

/** Handles mime type content negotiation */
class Accept extends AbstractAccept
{
    const string ACCEPT_HEADER = 'HTTP_ACCEPT';

    protected function authorize(string $requested, string $provided): mixed
    {
        if ($requested === $provided || $requested === '*/*') {
            return $provided;
        }

        if (false !== strpos($requested, '/')) {
            [$requestedA, $requestedB] = explode('/', $requested);
            [$providedA] = explode('/', $provided);

            if ($providedA === $requestedA && $requestedB === '*') {
                return $providedA;
            }
        }

        return parent::authorize($requested, $provided);
    }
}
