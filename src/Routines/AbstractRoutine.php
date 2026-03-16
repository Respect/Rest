<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use InvalidArgumentException;

/** Base class for callback routines */
abstract class AbstractRoutine implements Routinable
{
    protected mixed $callback;

    public function __construct(mixed $callback)
    {
        if (is_string($callback) && class_exists($callback) && method_exists($callback, '__invoke')) {
            $this->callback = $callback;

            return;
        }

        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Routine callback must be... guess what... callable!');
        }

        $this->callback = $callback;
    }

    protected function getCallback(): mixed
    {
        return $this->callback;
    }
}
