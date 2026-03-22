<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

/** Base class for callback routines */
// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousAbstractClassNaming.SuperfluousPrefix
abstract class AbstractRoutine implements Routinable
{
    /** @var callable */
    protected $callback;

    public function __construct(callable $callback)
    {
        $this->callback = $callback;
    }

    protected function getCallback(): callable
    {
        return $this->callback;
    }
}
