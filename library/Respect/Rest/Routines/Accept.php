<?php

namespace Respect\Rest\Routines;

use SplObjectStorage;
use Respect\Rest\Request;

class Accept extends AbstractRoutine implements ProxyableWhen, ProxyableThrough
{

    protected $mimeTypes = array();
    protected $negotiated = null;

    public function __construct($mimeType1, $mimeType2=null, $etc=null)
    {
        $this->negotiated = new SplObjectStorage;
        $this->mimeTypes = func_get_args();
    }

    protected function negotiate(Request $request)
    {
        $acceptHeader = $_SERVER['HTTP_ACCEPT'];
        $acceptParts = explode(',', $acceptHeader);
        $acceptMimes = array();
        foreach ($acceptParts as &$acceptPart) {
            list($mime, $quality) = explode(';q=', trim($acceptPart));
            $acceptMimes[$mime] = $quality;
        }
        arsort($acceptMimes);
        foreach ($this->mimeTypes as $mime)
            foreach ($acceptMimes as $accepted)
                if ($mime == $accepted)
                    return $this->negotiated[$request] = $mime;

        return false;
    }

    public function when(Request $request, $params)
    {
        return false !== $this->negotiate($request);
    }

    public function through(Request $request, $params)
    {
        if (!$this->negotiated[$request])
            return;
        
        //TODO
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

