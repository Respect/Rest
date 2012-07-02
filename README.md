Respect\Rest [![Build Status](https://secure.travis-ci.org/Respect/Rest.png)](http://travis-ci.org/Respect/Rest)
============

Thin controller for RESTful applications and APIs.

 * Very thin and lightweight.
 * Don't try to change PHP, small learning curve.
 * Completely RESTful, the right way to build apps.

Installation
------------

Packages available on [PEAR](http://respect.li/pear) and [Composer](http://packagist.org/packages/Respect/Rest). Autoloading is [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) compatible.

Feature Guide
-------------

### Configuration

Bootstrapping is easy. Just create an instance of Respect\Rest\Router.

    <?php

    use Respect\Rest\Router;

    $r3 = new Router;

This assumes you have an `.htaccess` file that redirects every request to this PHP file and
you're running this from the domain root (http://example.com/ without any subfolder).

If you want to use it from a subfolder, you can pass the virtual root to the Router:

    $r3 = new Router('/myapp');

This will instruct the router to work from http://example.com/myapp/.

You can also use the Router without an `.htaccess` file. This uses the CGI `PATH_INFO` variable,
and can be declared as:

    $r3 = new Router('/index.php/');

Also using folders:

    $r3 = new Router('/myapp/index.php/');

This assumes that every URL in the project will begin with these namespaces.

### Dispatching

The Router is auto-dispatched, which means that you don't have to call anything more than
declaring routes to run it. If you want to ommit this behavior, you can set:

    $r3->isAutoDispatched = false;

You can then dispatch it yourself at the end of the proccess:

    print $r3->run();

You can print the output or store in a variable if you want. This allows you to better
test and integrate the Router into existing applications.

### Simple Routing

The Hello World route is something like this:

    $r3->get('/', function() {
        return 'Hello World';
    });

Hitting `http://localhost/` (consider your local configuration for this) will print
"Hello World" on the browser. You can declare as many routes as you want:

    $r3->get('/hello', function() {
        return 'Hello from Path';
    });

Hitting `http://localhost/hello` will now print "Hello from Path".

### Using Parameters

You can declare routes that receives parameters by the URL. For this, every parameter
is a `/*` on the route path. Considering the previous sample model:

    $r3->get('/users/*', function($screenName) {
        echo "User {$screenName}";
    });

Accessing `http://localhost/users/alganet` with any username instead of `alganet` will
now print "User alganet" (or any username you pass to it).

Multiple parameters can be defined:

    $r3->get('/users/*/lists/*', function($user, $list) {
        return "List {$list} from user {$user}.";
    });

Last parameters on the route path are optional by default, so declaring just
a `->get('/posts/*'` will match for `http://localhost/posts/` without any
parameter. You can declare a second `->get('/posts'`, then the Router will
match it properly, or treat the missing parameter yourself by making them
`null`able on the passed function:

    $r3->get('/posts/*/*/*', function($year,$month=null,$day=null) {
        //list posts, month and day are optional
    });

 1. This will match /posts/2010/10/10, /posts/2011/01 and /posts/2010
 2. Optional parameters are allowed only on the end of the route path. This
    does not allow optional parameters: `/posts/*/*/*/comments/*`

### Catch-all Parameters

Sometimes you need to catch an undefined number of parameters. You can use
Routes with catch-all parameters like this:

    $r3->get('/users/*/documents/**', function($user, $documentPath) {
        return readfile(PATH_STORAGE. implode('/', $documentPath));
    });

 1. The above sample will match `/users/alganet/documents/foo/bar/baz/anything`.
    Callback $user parameter will receive alganet and $documentPath will
    receive an array filled with [foo,bar,baz,anything].
 2. Catch-all parameters are defined by a double asterisk `/**`.
 3. Catch-all parameters must appear only on the end of the path. Double
    asterisks in any other position will be converted to single asterisks.
 4. Catch-all parameters will match **after** any other route that matches
    the same pattern.

### Route Matching

Things now got more deeper. We got simple routes, routes with parameters, optional 
parameters and catch-all parameters. A simple rule to keep in mind is that Respect\Rest
matches the routes from the most specific to the most generic.

  * Routes with most slashes `/` are more specific and match first.
  * Routes with parameters are less specific than routes without parameters.
  * Routes with multiple parameters are even less specific.
  * Routes with catch-all parameters are the most generic ones.

Summing up: Slashes and asterisks places your route at the top to match first.

Respect\Rest does this automatically, but is highly recommended to declare routes
from the most specific to the most generic. This will improve performance and
manutenibility of the code.

### Matching any HTTP Method

Sometimes you need to use the router to proxy request to some other router or map
requests to a class. Using the `any` magic method you can pass any method to the given
function.

    $r3->any('/users/*', function($userName) {
        //do anything
    });

 1. Any HTTP method will match this same route.
 2. You can figure out the method using the standard PHP `$_SERVER['REQUEST_METHOD']`

### Class Controllers

The `any` is highly useful to bind classes to controllers, one of the Respect\Rest most 
awesome features:

    use Respect\Rest\Routable;

    class MyArticle implements Routable {
        public function get($id) { }
        public function delete($id) { }
        public function put($id) { }
    }

    $r3->any('/article/*', 'MyArticle');

  1. The above will bind the class methods to the HTTP methods using the same
     path.
  2. Parameters will be sent to the class methods just like the callbacks on
     the other samples.
  3. Controllers are lazy loaded and persistent. The *MyArticle* class will
     be instantiated only when a route matches one of his methods, and this
     instance will be reused on other requests (redirects, etc).
  4. Classes must implement the interface Respect\Rest\Routable for safety reasons.
     (Imagine someone mapping HTTP to a PDO class automatically, that wouldn't be right).

Passing construtor arguments to the class is also possible:

    $r3->any('/images/*', 'ImageController', array($myImageHandler, $myDb));

  1. This will pass `$myImageHandler` and `$myDb` as parameters for the
     *ImageController* class constructor.

You can also instantiate the class yourself if you want:

    $r3->any('/downloads/*', $myDownloadManager);

  1. Sample above will assign the existent `$myDownloadManager` as a controller.
  2. This instance is also reused by Respect\Rest

And you can even use a factory or DI container to build the controller class:

    $r3->any('/downloads/*', 'MyControllerClass', array('Factory', 'getController'));

  1. Sample above will use the MyController class returned by Factory::getController
  2. This instance is also reused by Respect\Rest
  3. Third parameter is any _callable_ variable, so you can put a closure there to build
     an instance if you want.

### Routing Streams

Sometimes you need to route users to streams. The Router doesn't need to handle
large files or wait for streams to finish to begin serving them.

    $r3->get('/images/*/hi-res', function($imageName) {
        header('Content-type: image/jpg');
        return fopen("/path/to/hi/images/{$imageName}.jpg", 'r');
    });

This will redirect the file directly to the browser without keeping it in
memory.

CAUTION: We did a very wrong thing in the sample: passing a parameter
directly to a `fopen` handle. Please validate the parameter before using it. This 
is demonstrational only.

### Routing Static Values

No secret here, you can make a route return a plain string:

    $r3->get('/greetings', 'Hello!');

### Forwarding Routes

Respect\Rest has an internal forwarding mechanism. First you need to know that
every route declaration returns an instance:

    $usersRoute = $r3->any('/users', 'UsersController');

Then you can `use` and `return` this route in another one:

    $r3->any('/premium', function($user) use ($db, $usersRoute) {
        if (!$db->userPremium($user)) {
          return $usersRoute;
        }
    });

Illustrative sample above will redirect internally when an user is not premium to 
another route that handle normal users.

### When Routine (if)

Respect\Rest uses a different approach to validate route parameters:

    $r3->get('/documents/*', function($documentId) {
        //do something
    })->when(function($documentId) {
        return is_numeric($documentId) && $documentId > 0;
    });

  1. This will match the route only if the callback on *when* is matched.
  2. The `$documentId` param must have the same name in the action and the
     condition (but does not need to appear in the same order).
  3. You can specify more than one parameter per condition callback.
  4. You can specify more than one callback: `when($cb1)->when($cb2)->when($etc)`
  5. Conditions will also sync with parameters on binded classes and instances
     methods.

This makes possible to the user to validate parameters using any custom routine and
not just data types as `int` or `string`.

We highly recommend you to use a strong validation library when using this. Consider
[Respect\Validation](http://github.com/Respect/Validation).

    $r3->get('/images/*/hi-res', function($imageName) {
        header('Content-type: image/jpg');
        return fopen("/path/to/hi/images/{$imageName}.jpg", 'r');
    })->when(function($imageName) {
        // Using Respect Validation alias to `V`
        return V::alphanum(".")->length(5,155)
                ->noWhitespace()->validate($imageName);
    });

### By Routine (before)

Sometimes you need to run something before a route does its job. This is 
useful for logging, authentication and similar purposes.

    $r3->get('/artists/*/albums/*', function($artistName, $albumName) {
        //do something
    })->by(function($albumName) use ($myLogger) {
        $myLogger->logAlbumVisit($albumName);
    });

  1. This will execute the callback defined on *by* before the route action.
     The route needs to match.
  2. Parameters are also synced by name, not order, like `when`.
  3. You can specify more than one parameter per proxy callback.
  4. You can specify more than one proxy: `by($cb1)->by($cb2)->by($etc)`
  5. A `return false` on a proxy will stop the execution of following proxies
     and the route action.
  6. Proxies will also sync with parameters on binded classes and instances
     methods.

If your By routine returns `false`, then the route method/function will not be
processed. If you return an instance of another route, an internal forward
will be performed.

### Trough Routine (after)

Similar to `->by`, but runs after the route did its job. In the sample
below we're showing something similar to invalidating a cache after
saving some new information.

    $r3->post('/artists/*/albums/*', function($artistName, $albumName) {
        //save some artist info
    })->through(function() use($myCache) {
        $myCache->clear($artistName, $albumName);
    });

  1. `by` proxies will be executed before the route action, `through proxies`
     will be executed after.
  2. You don't need to use them both at the same time.
  3. `through` can also receive parameters by name.

Sample above allows you to do something based on the route parameters, but when
procesing something after the route has run, its desirable to process its output
as well. This can be achieved with a nested closure:

    $r3->any('/settings', 'SetingsController')->through(function(){
        return function($data) {
            if (isset($settings['admin_user'])) {
                unset($settings['admin_user']);
            }
            return $data;
        };
    });

The illustrative sample above removes sensitive keys from a settings controller before
outputing the result.

### Controller Splitting

Using routines is encouraged to separate the controller logic into components. You can 
reuse them:

    $logRoutine = function() use ($myLogger, $r3) {
        $myLogger->logVisit($r3->request->path);
    };

    $r3->any('/users', 'UsersController')->by($logRoutine);
    $r3->any('/products', 'ProductsController')->by($logRoutine);

A simple way of applying routines to every route on the router is:

    $r3->always('By', $logRoutine);

You can use the param sync to get advantage of this:

    $r3->always('When', function($user=null) {
        if ($user) {
          return strlen($user) > 3;
        }
    });
    
    $r3->any('/products', function () { /***/ });
    $r3->any('/users/*', function ($user) { /***/ });
    $r3->any('/users/*/products', function ($user) { /***/ });
    $r3->any('/listeners/*', function ($user) { /***/ });

Since there are three routes with the `$user` parameter, `when` will
verify them all automatically by its name.

### Content Negotiation

Respect currently supports the four distinct types of content-negotiation: 
Mimetype, Encoding, Language and Charset. Usage sample:

    $r3->get('/about', function() {
        return array('v' => 2.0);
    })->acceptLanguage(array(
        'en' => function($data) { return array("Version" => $data['v']); },
        'pt' => function($data) { return array("Versão"  => $data['v']); }
    ))->accept(array(
        'text/html' => function($data) {
            list($k,$v)=each($data);
            return "<strong>$k</strong>: $v";
        },
        'application/json' => 'json_encode'
    ));

As in every routine, conneg routines are executed in the same order that
you appended them to the route. You can also use `->always` to apply this
routine to every route on the Router.

Please note that when returning streams, conneg routines are also called.
You can take advantage of this processing streams. The hardcore sample
below serves a text using the deflate encoding directly to the browser:

    $r3->get('/text/*', function($filename) {
      return fopen('data/'.$filename, 'r+');
    })->acceptEncoding(array(
        'deflate' => function($stream) {
            stream_filter_append($stream, 'zlib.deflate', STREAM_FILTER_READ);
            return $stream; //now deflated on demand 
        }
    ));

When applying conneg routines to multiple routes that can return streams you
(really) should check for `is_resource()` before doing anything.

### Basic HTTP Auth

Support for Basic HTTP Authentication is already implemented as a routine:

    $r3->get('/home', 'HomeController')->authBasic('My Realm', function($user, $pass) {
        return $user === 'admin' && $user === 'p4ss';
    }); 

You'll receive an username and password provided by the user, and you just need
to return true or false. True means that the user could be authenticated.

Respect\Rest will handle the authentication flow, sending the appropriate
headers when unauthenticated. You can also return another route, which will
act as a internal forward (see the section about this above).

### Filtering Browsers

Below is an illustrative sample of how to block requests from mobile devices:

    $r3->get('/videos/*', 'VideosController')->userAgent(array(
        'iphone|android' => function(){
            header('HTTP/1.1 403 Forbidden');
            return false; //do not process the route.
        }
    ));

You can pass several itens on the array, like any conneg routine. The array
key is a regular expression matcher without delimiters.

### Input Content-Type

Note that this is not currently implemented.

By default, HTML forms send POST data as `multipart/form-data`, but API clients
may send any other format. PUT requests often send other mime types. You can pre-process
this data before doing anything:

    $r3->post('/timeline', function() {
        return file_get_contents('php://input');
    })->contentType(array(
        'multipart/form-data' => function($input) {
            parse_str($input, $output);
            return $output;
        },
        'application/json' => function($input) {
            return my_json_converter($input);
        },
        'text/xml' => function($input) {
            return my_xml_converter($input);
        },
    ));

### HTTP Errors

Respect\Rest currently implement these errors by default:

  * 404, when no matching route path is found.
  * 401, when the client sends an unauthenticated request to a route using `authBasic` routine.
  * 405, when a matching path is found but the method don't.
  * 400, when a `when` validation fails.
  * 406, when the route path and method matches but content-negotiation don't.

### RESTful Extras

  * A HEAD request automatically works sending all GET headers without body. You can override 
    this behavior declaring custom `head` routes.
  * An OPTIONS request to `*` or any route path returns the `Allow` headers properly.
  * When returning 405, `Allow` headers are also set properly.

### Anti-Patterns

  * You can set `$r3->methodOverriding = true` to allow `?_method=ANYMETHOD` on the URI to
    override default HTTP methods. This is `false` by default.

### Your Own Routines

Routines are classes in the Respect\Rest\Routines namespace, but you can add your
own routines by instance using:

    $r3->get('/greetings', 'Hello World')->appendRoutine(new MyRoutine);

In the sample above, `MyRoutine` is a provided user routine declared as a class and
appended to the router. Custom routines have several different interfaces that can be implemented:

  * IgnorableFileExtension - Instructs the router to ignore the file extension in requests
  * ParamSynced - Sycs parameters with the route function/method.
  * ProxyableBy - Instructs the router to run method `by()` before the route.
  * ProxyableThrough - Instructs the router to run method `through()` after the route.
  * ProxyableWhen - Instructs the router to run method `when()` to validate the route match.
  * Unique - Makes this routine be replaced, not appended, if more than one is declared for
    the same type.

You can use any combination of the above, and you also need to implement Routinable.

