<?php
namespace Respect\Rest\Routines;

use \UnexpectedValueException;
use \ArrayAccess;

 class AbstractCallbackList implements ArrayAccess, Routinable
 {
    /** hide the collection completely use accessor methods */
    private $callbackList = array();

    /** filters out non callable from the list, step copy to new storage */
    public function __construct(array $list = array())
    {
        if (!($callbackList = array_filter($list, 'is_callable')))
            throw new UnexpectedValueException('Invalid setting: Not a single callable argument for callback routines: '. get_class($this));

        foreach ($callbackList as $acceptSpec => $callback)
            if (true === is_callable($callback))
                $this[$acceptSpec] = $callback;
            else
                error_log("The $acceptSpec enry does not have a valid callback configured, it has been ignored.\n", 1);
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
        return array_keys($this->callbackList);
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
        return $this->callbackList[$key];
    }

    protected function executeCallback($key, $params)
    {
        return call_user_func_array($this->callbackList[$key], $params);
    }

    /**
     * Only for suger, nothing else
     */
    public function offsetExists($offset)
    {   isset($this->$offset);
    }
    public function offsetGet($offset)
    {   return $this->$offset;
    }
    public function offsetSet($offset, $value)
    {   $this->$offset = $value;
    }
    public function offsetUnset($offset)
    {   unset($offset);
    }
    public function __get($key)
    {   return $this->callbackList[$key];
    }
    public function __set($key,$value)
    {   $this->callbackList[$key] = $value;
    }
    public function __isset($key)
    {   return isset($this->callbackList[$key]);
    }
    public function __unset($key)
    {   unset($this->callbackList[$key]);
    }

}
