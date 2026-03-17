<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use function explode;
use function preg_replace;
use function stripos;

/** Handles Language content negotiation */
final class AcceptLanguage extends AbstractAccept
{
    public const string ACCEPT_HEADER = 'HTTP_ACCEPT_LANGUAGE';

    protected function authorize(string $requested, string $provided): mixed
    {
        $requested = (string) preg_replace('/^x\-/', '', $requested);
        $provided = (string) preg_replace('/^x\-/', '', $provided);

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
