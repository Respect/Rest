<?php

namespace Respect\Rest;

use InvalidArgumentException;

class Route
{

    protected $regex;
    protected $callback;
    protected $method;
    protected $proxies = array();

    public function __construct($method, $regex, $callback)
    {
        $this->method = $method;
        $this->regex = $regex;
        $this->callback = $callback;
    }

    public function __invoke()
    {
        $params = func_get_args();

        foreach ($this->proxies as $proxy)
            if (false === call_user_func_array($proxy, &$params))
                return false;

        return call_user_func_array($this->callback, $params);
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getRegex()
    {
        return $this->regex;
    }

    public function match($uri, &$params=array())
    {
        return preg_match($this->regex, $uri, $params);
    }

    public function setProxies(array $proxies)
    {
        if (!array_filter($proxies, 'is_callable'))
            throw new InvalidArgumentException('Route proxies must be callable');

        $this->proxies = $proxies;
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