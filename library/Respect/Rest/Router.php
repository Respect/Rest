<?php

namespace Respect\Rest;

class Router
{

    protected $routes = array();

    public function addRoute($httpMethod, $path, $callback)
    {
        $httpMethod = strtoupper($httpMethod);
        $pathRegex = $this->compileRouteRegex($path);
        $this->routes[$httpMethod][$pathRegex] = $callback;
        uksort($this->routes[$httpMethod],
            function($a, $b) {
                return substr_count($a, '.+?') < substr_count($b, '.+?');
            }
        );
    }

    protected function compileRouteRegex($path)
    {
        $path = rtrim($path, ' /');
        $extra = $this->extractCatchAllPattern($path);
        $pathQuoted = str_replace('/\*', '/(.+?)', preg_quote($path));
        $pathQuoted = $this->compileOptionalParams($pathQuoted);
        $pathRegex = "#^{$pathQuoted}{$extra}$#";
        return $pathRegex;
    }

    protected function extractCatchAllPattern(&$path)
    {
        $extra = '(/.*)?';

        if (strlen($path) - 3 === strripos($path, '/**'))
            $path = substr($path, 0, -3);
        else
            $extra = '';

        $path = str_replace('**', '*', $path);
        return $extra;
    }

    //turn sequenced parameters optional, so /*/*/* will match /1/2/3, /1/2 and /1
    protected function compileOptionalParams($pathQuoted)
    {
        while (strlen($pathQuoted) - 12 === strripos($pathQuoted, '/(.+?)/(.+?)'))
            $pathQuoted = str_replace(
                    array('/(.+?)/(.+?)', '(?:/(.+?))?/(.+?)'),
                    array('/(.+?)(?:/(.+?))?', '(?:/(.+?))?(?:/(.+?))?'),
                    $pathQuoted
            );
        return $pathQuoted;
    }

    public function dispatch($httpMethod=null, $httpUri=null)
    {
        $httpMethod = strtoupper($httpMethod ? : $_SERVER['REQUEST_METHOD']);
        $httpUri = $httpUri ? : parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $httpUri = rtrim($httpUri, ' /');
        if ($this->routes[$httpMethod])
            foreach ($this->routes[$httpMethod] as $pathRegex => $callback)
                if (preg_match($pathRegex, $httpUri, $params))
                    return call_user_func_array(
                        $callback, array_filter(array_slice($params, 1))
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