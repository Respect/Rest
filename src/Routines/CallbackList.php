<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use UnexpectedValueException;
use ArrayObject;

/**
 * Facilitates the keyed callback lists for routines.
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class CallbackList extends ArrayObject implements Routinable
{
    /** filters out non callable from the list, step copy to new storage */
    public function __construct(array $list = [])
    {
        $this->setFlags(self::ARRAY_AS_PROPS);

        if (!($callbackList = array_filter($list, 'is_callable'))) {
            $message = 'Invalid setting: Not a single callable argument for callback routines: '.get_class($this);
            throw new UnexpectedValueException($message);
        }

        foreach ($callbackList as $acceptSpec => $callback) {
            if (true === is_callable($callback)) {
                $this[$acceptSpec] = $callback;
            } else {
                error_log("The $acceptSpec entry does not have a valid callback configured, it has been ignored.\n", 1);
            }
        }
    }

    public function getKeys(): array
    {
        return array_keys($this->getArrayCopy());
    }

    public function hasKey(string $key): bool
    {
        return isset($this->$key);
    }

    public function filterKeysContain(string $needle): array
    {
        return array_filter($this->getKeys(), function ($key) use ($needle) {
            return false !== strpos($key, $needle);
        });
    }

    public function filterKeysNotContain(string $needle): array
    {
        return array_filter($this->getKeys(), function ($key) use ($needle) {
            return false === strpos($key, $needle);
        });
    }

    protected function getCallback(string $key): callable
    {
        return $this->$key;
    }

    protected function executeCallback(string $key, array $params): mixed
    {
        return ($this->$key)(...$params);
    }
}
