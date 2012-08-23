<?php

namespace Respect\Rest;

class Param
{

    const GET    = 2;
    const POST   = 4;
    const COOKIE = 16;

    private $type;

    public function __construct($type)
    {
        $this->type = (int) $type;
    }

    public static function getAvailableTypes()
    {
        return array(
            'GET'    => self::GET,
            'POST'   => self::POST,
            'COOKIE' => self::COOKIE
        );
    }
    
    public function getValue($param, $default = null)
    {
        foreach (self::getAvailableTypes() as $name => $type) {
            if (($this->hasType($type) && !isset($_REQUEST[$name][$param]))
                    || !$this->hasType($type)) {
                continue;
            }
            $default = $_REQUEST[$name][$param];
            break;
        }
        
        return $default;
    }
    
    public function getValues()
    {
        $values = array();
        foreach (self::getAvailableTypes() as $name => $type) {
            if (!$this->hasType($type)) {
                continue;
            }
            $values += $_REQUEST[$name];
        }
        
        return $values;
    }

    private function hasType($type)
    {
        return $type === ($type & $this->type);
    }

    private function isType($type)
    {
        return ($this->type === $type);
    }
    
    public function isGet()
    {
        return $this->isType(self::GET);
    }

    public function isPost()
    {
        return $this->isType(self::POST);
    }

    public function isCookie()
    {
        return $this->isType(self::COOKIE);
    }


}