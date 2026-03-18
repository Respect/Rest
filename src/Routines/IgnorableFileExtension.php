<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

interface IgnorableFileExtension
{
    /** @return array<int, string> Extensions this routine handles, e.g. ['.json', '.html'] */
    public function getExtensions(): array;
}
