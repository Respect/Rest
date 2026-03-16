<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routines;

use UnexpectedValueException;
use ArrayObject;

/**
 * Facilitates the keyed callback lists for routines.
 * @author Nick Lombard <github@jigsoft.co.za>
 */
class AbstractCallbackList extends ArrayObject implements Routinable
{
    /** filters out non callable from the list, step copy to new storage */
    public function __construct(array $list = array())
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
                error_log("The $acceptSpec enry does not have a valid callback configured, it has been ignored.\n", 1);
            }
        }
    }

    /**
     * Public accessor methods, free for all (idempotent)
     *
     * @method getKeys to retrieve only the keys for conneg etc.
     * @method hasKey check if key is present
     * @method filterKeysContain fetch keys matching supplied string
     * @method filterKeysNotContain  fetch keys that don't include string
     */
    public function getKeys()
    {
        return array_keys($this->getArrayCopy());
    }
    public function hasKey($key)
    {
        return isset($this->$key);

        return array_key_exists($key, $this);
    }
    public function filterKeysContain($needle)
    {
        return array_filter($this->getKeys(), function ($key) use ($needle) {
            return false !== strpos($key, $needle);
        });
    }
    public function filterKeysNotContain($needle)
    {
        return array_filter($this->getKeys(), function ($key) use ($needle) {
            return false === strpos($key, $needle);
        });
    }

    /**
     * Protected accessor methods, members only.
     *
     * @method getCallback return the configured callback associated with key
     * @method executeCallback and forward supplied parmaters
     */
    protected function getCallback($key)
    {
        return $this->$key;
    }

    protected function executeCallback($key, $params)
    {
        return call_user_func_array($this->$key, $params);
    }
}
