<?php

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Respect\Rest\Routable;

class ClassName extends AbstractRoute
{

    protected $class = '';
    protected $constructorParams = array();
    protected $instance = null;
	protected $postFunction = null;

    public function __construct($method, $pattern, $class, $constructorParams, $posFunction)
    {
        $this->class = $class;
        $this->constructorParams = $constructorParams;
		$this->posFunction = $posFunction;
        parent::__construct($method, $pattern);
    }

    protected function createInstance()
    {
        $className = $this->class;

        $reflection = new ReflectionClass($className);
        if (!$reflection->implementsInterface('Respect\\Rest\\Routable'))
            throw new InvalidArgumentException(''); //TODO

		$instance = null;
		
        if (empty($this->constructorParams) || !method_exists($this->class, '__construct')) {
			$instance = new $className;
		} else {
	        $reflection = new ReflectionClass($this->class);
	        $instance = $reflection->newInstanceArgs($this->constructorParams);			
		}
        
		$c = $this->posFunction;
		if (is_callable($c))
			$c($instance);
			
		return $instance;
    }

    public function getReflection($method)
    {
        try {
            return new ReflectionMethod($this->class, $method);
        } catch (ReflectionException $e) {
            return false;
        }
    }

    public function runTarget($method, &$params)
    {
        if (is_null($this->instance))
            $this->instance = $this->createInstance();

        return call_user_func_array(
            array($this->instance, $method), $params
        );
    }

}

/**
 * LICENSE
 *
 * Copyright (c) 2009-2011, Alexandre Gomes Gaigalas.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 *     * Redistributions of source code must retain the above copyright notice,
 *       this list of conditions and the following disclaimer.
 *
 *     * Redistributions in binary form must reproduce the above copyright notice,
 *       this list of conditions and the following disclaimer in the documentation
 *       and/or other materials provided with the distribution.
 *
 *     * Neither the name of Alexandre Gomes Gaigalas nor the names of its
 *       contributors may be used to endorse or promote products derived from this
 *       software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 */