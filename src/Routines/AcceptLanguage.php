<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

/** Handles Language content negotiation */
class AcceptLanguage extends AbstractAccept
{
    const string ACCEPT_HEADER = 'HTTP_ACCEPT_LANGUAGE';

    protected function authorize(string $requested, string $provided): mixed
    {
        $requested = preg_replace('/^x\-/', '', $requested);
        $provided = preg_replace('/^x\-/', '', $provided);

        if ($requested == $provided) {
            return $provided;
        }

        if (stripos($requested, '-') || !stripos($provided, '-')) {
            return false;
        }

        [$providedA] = explode('-', $provided);

        if ($requested === $providedA) {
            return $providedA;
        }

        return parent::authorize($requested, $provided);
    }
}
