Respect\Rest
============

Thin controller for RESTful applications

**This is a work in progress and has not been tested on production environments**

Highlights:

 * Thin. Does not try to change how PHP works (`$_POST`, `$_GET`, etc remains untouched by default);
 * Lightweight. Currently under 300 lines of PHP.
 * Maintainable. You can migrate from the microframework style to the class-controller style.
 * RESTful. The right way to create web apps.


Feature Guide
=============

Configuration
-------------

    <?php

    use Respect\Rest\Router;

    $r3 = new Router;

Simple Routing
--------------

    $r3->get('/', function() {
        return 'Hello World';
    });

    echo $r3->dispatch();

 1. *get* is the equivalent of the HTTP GET method. You can use post, put, delete
    or any other. You can even use custom methods if you want.
 2. *return* sends the output string to the dispatcher

Parameters
----------

    $r3->get('/users/*', function($screen_name) {
        return "Hello {$screen_name}";
    });

 1. You can ommit the `echo $r3->dispatch();` if you want. It will be called
    automatically.
 2. Parameters are defined by a `/*` on the route path and passed to the
    callback function in the same order as they appear

Multiple Parameters
-------------------

    $r3->get('/users/*/lists/*', function($user, $list) {
        return "List {$list} from user {$user}";
    });

Optional Parameters
-------------------

    $r3->get('/posts/*/*/*', function($year,$month=null,$day=null) {
        //list posts
    });

 1. This will match /posts/2010/10/10, /posts/2011/01 and /posts/2010
 2. Optional parameters are allowed only on the end of the route path. This
    does not allow optional parameters: `/posts/*/*/*/comments/*`

Catch-all Parameters
--------------------

    $r3->get('/user/*/documents/**', function($user, $documentPath) {
        return readfile(PATH_STORAGE.$documentPath);
    });

 1. The above sample will match `/user/alganet/documents/foo/bar/baz/anything`,
    and the entire string will be passed as a single parameter to the callback;
 2. Catch-all parameters are defined by a double asterisk \*\*.
 3. Catch-all parameters must appear only on the end of the path. Double
    asterisks in any other position will be converted to single asterisks.

Bind Controller Classes
-----------------------

    class MyArticle {
        public function get($id) { }
        public function delete($id) { }
        public function put($id) { }
    }

    $r3->addController('/article/*', 'MyArticle');

  1. The above will bind the class methods to the HTTP methods using the same
     path.
  2. Parameters will be sent to the class methods just like the callbacks on
     the other samples.
  3. Controllers are lazy loaded and persistent. The *MyArticle* class will
     be instantiated only when a route matches one of his methods, and this
     instance will be reused on other requests (redirects, etc).

Controller Classes Constructors
-------------------------------

    $r3->addController('/images/*', 'ImageController', $myImageHandler, $myDb);

  1. This will pass `$myImageHandler` and `$myDb` as parameters for the
     *ImageController* class.

Direct Instances
----------------

    $r3->addController('/downloads/*', $myDownloadManager);

  1. Sample above will assign the existent `$myDownloadManager` as a controller.
  2. This instance is also reused by Respect\Rest


License Information
===================

Copyright (c) 2009-2011, Alexandre Gomes Gaigalas.
All rights reserved.

Redistribution and use in source and binary forms, with or without modification,
are permitted provided that the following conditions are met:

* Redistributions of source code must retain the above copyright notice,
  this list of conditions and the following disclaimer.

* Redistributions in binary form must reproduce the above copyright notice,
  this list of conditions and the following disclaimer in the documentation
  and/or other materials provided with the distribution.

* Neither the name of Alexandre Gomes Gaigalas nor the names of its
  contributors may be used to endorse or promote products derived from this
  software without specific prior written permission.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
(INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
(INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.

