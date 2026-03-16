<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

use InvalidArgumentException;

/** Base class for callback routines */
abstract class AbstractRoutine implements Routinable
{
    protected $callback;

    public function __construct($callback)
    {
        if (is_string($callback) && class_exists($callback) && method_exists($callback, '__invoke')) {
            return $this->callback = $callback;
        }

        if (!is_callable($callback)) {
            throw new InvalidArgumentException('Routine callback must be... guess what... callable!');
        }

        $this->callback = $callback;
    }

    protected function getCallback()
    {
        return $this->callback;
    }
}
