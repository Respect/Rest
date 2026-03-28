<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use ArrayObject;
use UnexpectedValueException;

use function array_filter;
use function array_keys;

/**
 * Facilitates the keyed callback lists for routines.
 *
 * @extends ArrayObject<string, callable>
 */
class CallbackList extends ArrayObject implements Routinable
{
    /**
     * filters out non callable from the list, step copy to new storage
     *
     * @param array<string, callable> $list
     */
    public function __construct(array $list = [])
    {
        $this->setFlags(self::ARRAY_AS_PROPS);

        $callbackList = array_filter($list, 'is_callable');

        if (!$callbackList) {
            $message = 'Invalid setting: Not a single callable argument for callback routines: ' . static::class;

            throw new UnexpectedValueException($message);
        }

        foreach ($callbackList as $acceptSpec => $callback) {
            $this[$acceptSpec] = $callback;
        }
    }

    /** @return array<int, string> */
    public function getKeys(): array
    {
        return array_keys($this->getArrayCopy());
    }

    protected function getCallback(string $key): callable
    {
        return $this->$key;
    }
}
