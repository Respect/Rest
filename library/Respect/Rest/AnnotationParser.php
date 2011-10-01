<?php

namespace Respect\Rest;

class AnnotationParser
{
    protected $class;
    
    public function __construct(\ReflectionClass $class)
    {
        $this->class = $class;
    }
    
    public function parseDependencies()
    {
        $dependencies = array();
        
        foreach ($this->class->getProperties() as $prop)
            if ($this->matchDependency($prop, $matches))
                $dependencies[] = array('property' => $prop->getName(), 'dependency' => $matches[1]);
                
        return $dependencies;
    }
    
    protected function matchDependency($prop, &$matches)
    {
        return (bool) preg_match('/@dependency\s*\(([a-zA-Z0-9_ ]*)\)/i', $prop->getDocComment(), $matches);
    }
}