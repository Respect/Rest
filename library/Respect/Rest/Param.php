<?php

namespace Respect\Rest;

class Param
{

    const GET    = 2;
    const POST   = 4;
    const COOKIE = 16;

    private $type;
    private $values;

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
        $values = $this->getValues();
        if (isset($values[$param])) {
            $default = $values[$param];
        }
        
        return $default;
    }

    public function getValues()
    {
        if (null === $this->values) {
            
            $this->values = array();

            foreach (self::getAvailableTypes() as $type) {
                if (!$this->hasType($type)) {
                    continue;
                } elseif (self::GET === $type) {
                    $values = $_GET;
                } elseif (self::POST === $type) {
                    $values = $_POST;
                } elseif (self::COOKIE === $type) {
                    $values = $_COOKIE;
                }
                $this->values += $values;
            }

        }
        
        return $this->values;
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

    public function hasGet()
    {
        return $this->hasType(self::GET);
    }

    public function hasPost()
    {
        return $this->hasType(self::POST);
    }

    public function hasCookie()
    {
        return $this->hasType(self::COOKIE);
    }


}