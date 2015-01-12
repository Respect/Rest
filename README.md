# Respect\Rest
[![Build Status](https://img.shields.io/travis/Respect/Rest.svg?style=flat-square)](http://travis-ci.org/Respect/Rest)
[![Code Coverage](https://img.shields.io/scrutinizer/coverage/g/Respect/Rest.svg?style=flat-square)](https://scrutinizer-ci.com/g/Respect/Rest)
[![Latest Version](https://img.shields.io/packagist/v/respect/rest.svg?style=flat-square)](https://packagist.org/packages/respect/rest)
[![Total Downloads](https://img.shields.io/packagist/dt/respect/rest.svg?style=flat-square)](https://packagist.org/packages/respect/rest)
[![License](https://img.shields.io/packagist/l/respect/rest.svg?style=flat-square)](https://packagist.org/packages/respect/rest)

Thin controller for RESTful applications and APIs.

 * Very thin and lightweight.
 * Don't try to change PHP, small learning curve.
 * Completely RESTful, the right way to build apps.


## Installation

The package is available on [Packagist](https://packagist.org/packages/respect/rest).
You can install it using [Composer](http://getcomposer.org).

```bash
composer require respect/rest
```

## Feature Guide

### Navigation
[Configuration][] | [Dispatching][] | [Simple Routing][] | [Parameters][] | [Catch-all][] | [Matching][] | [Methods][] | [Controllers][] | [Streams][] | [Static][] | [Forwarding][] | [When][] | [By][] | [Through][] | [Controller Splitting][] | [Conneg][] | [Basic Auth][] | [User-Agent][] | [Content-Type][] | [HTTP Errors][] | [RESTful Extras][] | [Anti-Patterns][] | [Own Routines][] | [Error Handling][]

### Configuration
[Top][]

Bootstrapping is easy. Just create an instance of Respect\Rest\Router.
```php
use Respect\Rest\Router;

$r3 = new Router;
```

This assumes you have a `.htaccess` file that redirects every request to this PHP file and
you're running this from the domain root (http://example.com/ without any subfolder).

If you want to use it from a subfolder, you can pass the virtual root to the Router:
```php
$r3 = new Router('/myapp');
```

This will instruct the router to work from http://example.com/myapp/.

You can also use the Router without a `.htaccess` file. This uses the CGI `PATH_INFO` variable,
and can be declared as:
```php
$r3 = new Router('/index.php/');
```

The same goes for folders:
```php
$r3 = new Router('/myapp/index.php/');
```

This assumes that every URL in the project will begin with these namespaces.

### Dispatching
[Top][]

The Router is auto-dispatched, which means that you don't have to call anything more than
declaring routes to run it. If you want to omit this behavior, you can set:
```php
$r3->isAutoDispatched = false;
```

Note that you need the following step in order to see uncaught Exceptions in your application
output. Standard error output on logs is untouched.

You can then dispatch it yourself at the end of the proccess:
```php
print $r3->run();
```

You can print the output or store in a variable if you want. This allows you to better
test and integrate the Router into existing applications.

### Simple Routing
[Top][]

The Hello World route goes something like this:
```php
$r3->get('/', function() {
    return 'Hello World';
});
```

Hitting `http://localhost/` (consider your local configuration for this) will print
"Hello World" in the browser. You can declare as many routes as you want:
```php
$r3->get('/hello', function() {
    return 'Hello from Path';
});
```

Hitting `http://localhost/hello` will now print "Hello from Path".

### Using Parameters
[Top][]

You can declare routes that receives parameters from the URL. For this, every parameter
is a `/*` on the route path. Considering the previous sample model:
```php
$r3->get('/users/*', function($screenName) {
    echo "User {$screenName}";
});
```

Accessing `http://localhost/users/alganet` or any other username besides `alganet` will
now print "User alganet" (or the username of your choosing).

Multiple parameters can be defined:
```php
$r3->get('/users/*/lists/*', function($user, $list) {
    return "List {$list} from user {$user}.";
});
```

Last parameters on the route path are optional by default, so declaring just
a `->get('/posts/*'` will match `http://localhost/posts/` without any
parameter. You can declare a second `->get('/posts'`, now the Router will
match it properly, or treat the missing parameter yourself by making them
`null`able on the passed function:
```php
$r3->get('/posts/*/*/*', function($year,$month=null,$day=null) {
    /** list posts, month and day are optional */
});
```

 1. This will match /posts/2010/10/10, /posts/2011/01 and /posts/2010
 2. Optional parameters are allowed only on the end of the route path. This
    does not allow optional parameters: `/posts/*/*/*/comments/*`

### Catch-all Parameters
[Top][]

Sometimes you need to catch an undefined number of parameters. You can use
Routes with catch-all parameters like this:
```php
$r3->get('/users/*/documents/**', function($user, $documentPath) {
    return readfile(PATH_STORAGE. implode('/', $documentPath));
});
```

 1. The above sample will match `/users/alganet/documents/foo/bar/baz/anything`.
    Callback $user parameter will receive alganet and $documentPath will
    receive an array filled with [foo,bar,baz,anything].
 2. Catch-all parameters are defined by a double asterisk `/**`.
 3. Catch-all parameters must appear only on the end of the path. Double
    asterisks in any other position will be converted to single asterisks.
 4. Catch-all parameters will match **after** any other route that matches
    the same pattern.

### Route Matching
[Top][]

Things can became very complex quick. We have simple routes, routes with parameters, optional
parameters and catch-all parameters. A simple rule to keep in mind is that Respect\Rest
matches the routes from the most specific to the most generic.

  * Routes with the most slashes `/` are more specific and will be matched first.
  * Routes with parameters are less specific than routes without parameters.
  * Routes with multiple parameters are even less specific than a route with one parameter.
  * Routes with catch-all parameters are the least specific and will be matched last.

Summing up: Slashes and asterisks places your route at the top of the priority list to match first.

Respect\Rest sort routes automatically, but it is highly recommended to declare routes
from the most specific to the most generic. This will improve performance and
maintainability of the code.

### Multiple Routes
[Top][]

You may want to have multiple routes perform the same action. Pluralization is the most common reason for this. This can be done like so:
```php
$r3->get(array('/user/*', '/users/*'), function($userName) {
    return 'Hello '. $userName;
});
```

### Matching any HTTP Method
[Top][]

Sometimes you need to use a router to proxy requests to some other router or map
requests to a class. By using the magic method `any`, you can pass any HTTP method to a given
function.
```php
$r3->any('/users/*', function($userName) {
    /** do anything */
});
```

 1. Any HTTP method will match this same route.
 2. You can figure out the method using the standard PHP `$_SERVER['REQUEST_METHOD']`

### Class Controllers
[Top][]

The `any` method is extremely useful to bind classes to controllers, one of Respect\Rest's most
awesome features:
```php
use Respect\Rest\Routable;

class MyArticle implements Routable {
    public function get($id) { }
    public function delete($id) { }
    public function put($id) { }
}

$r3->any('/article/*', 'MyArticle');
```

  1. This route will bind the class methods to the HTTP methods for the given path.
  2. Parameters will be sent to the class methods just like the callbacks from
     the previous examples.
  3. Controllers are lazy loaded and persistent. The *MyArticle* class will
     be instantiated only when a route matches one of its methods, and this
     instance will be reused on subsequent callbacks (redirects, etc).
  4. Classes must implement the interface Respect\Rest\Routable for safety reasons.
     (Imagine someone mapping HTTP to a PDO class automatically, that wouldn't be right).

Passing constructor arguments to the class is also possible:
```php
$r3->any('/images/*', 'ImageController', array($myImageHandler, $myDb));
```

  1. This will pass `$myImageHandler` and `$myDb` as parameters for the
     *ImageController* class constructor.

You can also instantiate the class yourself if you want:
```php
$r3->any('/downloads/*', $myDownloadManager);
```

  1. Sample above will assign the existent `$myDownloadManager` as a controller.
  2. This instance is also reused by Respect\Rest

And you can even use a factory or DI container to build the controller class:
```php
$r3->any('/downloads/*', 'MyControllerClass', array('Factory', 'getController'));
```

  1. Sample above will use the MyController class returned by Factory::getController
  2. This instance is also reused by Respect\Rest
  3. Third parameter is any _callable_ variable, so you can put a closure there to build
     an instance if you want.

### Routing Streams
[Top][]

Sometimes you need to route users to streams. The Router doesn't have to first handle
large files or wait for streams to finish before serving them.
```php
$r3->get('/images/*/hi-res', function($imageName) {
    header('Content-type: image/jpg');
    return fopen("/path/to/hi/images/{$imageName}.jpg", 'r');
});
```

This will redirect the file directly to the browser without keeping it in
memory.

CAUTION: We created a possible security vulnerability in the sample: passing a parameter
directly to a `fopen` handle. Please validate user input parameters before using them.
This is for demonstrational purposes only.

### Routing Static Values
[Top][]

No surprises here, you can make a route return a plain string:
```php
$r3->get('/greetings', 'Hello!');
```

### Forwarding Routes
[Top][]

Respect\Rest has an internal forwarding mechanism. First you'll need to understand that
every route declaration returns an instance:
```php
$usersRoute = $r3->any('/users', 'UsersController');
```

Then you can `use` and `return` this route in another one:
```php
$r3->any('/premium', function($user) use ($db, $usersRoute) {
    if (!$db->userPremium($user)) {
      return $usersRoute;
    }
});
```

Illustrative sample above will redirect internally when an user is not privileged to
another route that handle normal users.

### When Routine (if)
[Top][]

Respect\Rest uses a different approach to validate route parameters:
```php
$r3->get('/documents/*', function($documentId) {
    /** do something */
})->when(function($documentId) {
    return is_numeric($documentId) && $documentId > 0;
});
// Routines can also be called using class and method names.
$r3->get('/documents/*', function($documentId) {
    /** do something */
})->when('SomeClass_name', 'someMethod_name');
// You can also pass any instance that implements the __invoke() magic method to any routine.
```

  1. This will match the route only if the callback on *when* is matched.
  2. The `$documentId` param must have the same name in the action and the
     condition (but does not need to appear in the same order).
  3. You can specify more than one parameter per condition callback.
  4. You can specify more than one callback: `when($cb1)->when($cb2)->when($etc)`
  5. Conditions will also sync with parameters on binded classes and instance
     methods.

This makes it possible for the user to validate parameters using any custom routine and
not just data types such as `int` or `string`.

We highly recommend that you use a strong validation library when using this. Consider
[Respect\Validation](http://github.com/Respect/Validation).
```php
$r3->get('/images/*/hi-res', function($imageName) {
    header('Content-type: image/jpg');
    return fopen("/path/to/hi/images/{$imageName}.jpg", 'r');
})->when(function($imageName) {
    /** Using Respect Validation alias to `V` */
    return V::alphanum(".")->length(5,155)
            ->noWhitespace()->validate($imageName);
});
```

### By Routine (before)
[Top][]

Sometimes you need to run something before a route does its job. This is
useful for logging, authentication and similar purposes.
```php
$r3->get('/artists/*/albums/*', function($artistName, $albumName) {
    /** do something */
})->by(function($albumName) use ($myLogger) {
    $myLogger->logAlbumVisit($albumName);
});
```

  1. This will execute the callback defined with *by* before the route action
     which needs to match a route.
  2. Parameters are also synced by name and not by order, like with `when`.
  3. You can specify more than one parameter per proxy callback.
  4. You can specify more than one proxy: `by($cb1)->by($cb2)->by($etc)`
  5. A `return false` from a proxy will stop the execution of any following proxies
     as well as the route action.
  6. Proxies will also sync with parameters on binded classes and instance
     methods.

If your By routine returns `false`, then the route method/function will not be
processed. If you return an instance of another route, an internal forward
will be performed.

### Through Routine (after)
[Top][]

Similar to `->by`, but runs after the route did its job. In the sample
below we're showing something similar to invalidating a cache after
saving some new information.
```php
$r3->post('/artists/*/albums/*', function($artistName, $albumName) {
    /** save some artist info */
})->through(function() use($myCache) {
    $myCache->clear($artistName, $albumName);
});
```

  1. `by` proxies will be executed before the route action, `through proxies`
     will be executed after.
  2. You are free to use them separately or in tandem.
  3. `through` can also receive parameters by name.

Sample above allows you to do something based on the route parameters, but when
procesing something after the route has run, its desirable to process its output
as well. This can be achieved with a nested closure:
```php
$r3->any('/settings', 'SetingsController')->through(function(){
    return function($data) {
        if (isset($settings['admin_user'])) {
            unset($settings['admin_user']);
        }
        return $data;
    };
});
```

The illustrative sample above removes sensitive keys from a settings controller before
outputing the result.

### Controller Splitting
[Top][]

When using routines you are encouraged to separate the controller logic into components. You can
reuse them:
```php
$logRoutine = function() use ($myLogger, $r3) {
    $myLogger->logVisit($r3->request->path);
};

$r3->any('/users', 'UsersController')->by($logRoutine);
$r3->any('/products', 'ProductsController')->by($logRoutine);
```

A simple way of applying routines to every route on the router is:
```php
$r3->always('By', $logRoutine);
```

You can use the param sync to take advantage of this:
```php
$r3->always('When', function($user=null) {
    if ($user) {
      return strlen($user) > 3;
    }
});

$r3->any('/products', function () { /***/ });
$r3->any('/users/*', function ($user) { /***/ });
$r3->any('/users/*/products', function ($user) { /***/ });
$r3->any('/listeners/*', function ($user) { /***/ });
```

Since there are three routes with the `$user` parameter, `when` will
verify them all automatically by its name.

### Content Negotiation
[Top][]

Respect/Rest currently supports the four distinct types of Accept header content-negotiation:
Mimetype, Encoding, Language and Charset. Usage sample:
```php
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
```

As in every routine, conneg routines are executed in the same order in which
you appended them to the route. You can also use `->always` to apply this
routine to every route on the Router.

Please note that when returning streams, conneg routines are also called.
You may take advantage of this when processing streams. The hardcore example
below serves text, using the deflate encoding, directly to the browser:
```php
$r3->get('/text/*', function($filename) {
  return fopen('data/'.$filename, 'r+');
})->acceptEncoding(array(
    'deflate' => function($stream) {
        stream_filter_append($stream, 'zlib.deflate', STREAM_FILTER_READ);
        return $stream; /** now deflated on demand */
    }
));
```

When applying conneg routines to multiple routes that can return streams you
(really) should check for `is_resource()` before doing anything.

### Basic HTTP Auth
[Top][]

Support for Basic HTTP Authentication is already implemented as a routine:
```php
$r3->get('/home', 'HomeController')->authBasic('My Realm', function($user, $pass) {
    return $user === 'admin' && $pass === 'p4ss';
});
```

You'll receive an username and password provided by the user, and you just need
to return true or false. True means that the user could be authenticated.

Respect\Rest will handle the authentication flow, sending the appropriate
headers when unauthenticated. You can also return another route, which will
act as an internal forward (see the section on forwarding above).

### Filtering Browsers
[Top][]

Below is an illustrative sample of how to block requests from mobile devices:
```php
$r3->get('/videos/*', 'VideosController')->userAgent(array(
    'iphone|android' => function(){
        header('HTTP/1.1 403 Forbidden');
        return false; /** do not process the route. */
    }
));
```

You can pass several items in the array, like any conneg routine. The array
key is a regular expression matcher without delimiters.

### Input Content-Type (input data)
[Top][]

Note that this is not currently implemented.

By default, HTML forms send POST data as `multipart/form-data`, but API clients
may send any other format. PUT requests often send other mime types. You can pre-process
this data before doing anything:
```php
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
```

### HTTP Errors
[Top][]

Respect\Rest currently handle the following errors by default:

  * 404, when no matching route paths are found.
  * 401, when the client sends an unauthenticated request to a route using `authBasic` routine.
  * 405, when a matching path is found but the method isn't specified.
  * 400, when a `when` validation fails.
  * 406, when the route path and method matches but content-negotiation doesn't.

### RESTful Extras
[Top][]

  * A HEAD request automatically works sending all GET headers without body. You can override
    this behavior declaring custom `head` routes.
  * An OPTIONS request to `*` or any route path returns the `Allow` headers properly.
  * When returning 405, `Allow` headers are also set properly.

### Anti-Patterns
[Top][]

  * You can set `$r3->methodOverriding = true` to allow `?_method=ANYMETHOD` on the URI to
    override default HTTP methods. This is `false` by default.

### Your Own Routines
[Top][]

Routines are classes in the Respect\Rest\Routines namespace, but you can add your
own routines by instance using:
```php
    $r3->get('/greetings', 'Hello World')->appendRoutine(new MyRoutine);
```

In the sample above, `MyRoutine` is a user provided routine declared as a class and
appended to the router. Custom routines have the option of several different interfaces
which can be implemented:

  * IgnorableFileExtension - Instructs the router to ignore the file extension in requests
  * ParamSynced - Syncs parameters with the route function/method.
  * ProxyableBy - Instructs the router to run method `by()` before the route.
  * ProxyableThrough - Instructs the router to run method `through()` after the route.
  * ProxyableWhen - Instructs the router to run method `when()` to validate the route match.
  * Unique - Makes this routine be replaced, not appended, if more than one is declared for
    the same type.

You can use any combination of the above but also need to implement the Routinable interface.

### Error Handling
[Top][]

Respect\Rest provides two special ways to handle errors. The first one is using Exception
Routes:

```php
$r3->exceptionRoute('InvalidArgumentException', function (InvalidArgumentException $e) {
    return 'Sorry, this error happened: '.$e->getMessage();
});
```

Whenever an uncaught exception appears on any route, it will be caught and forwarded to
this side route. Similarly, there is a route for PHP errors:


```php
$r3->errorRoute(function (array $err) {
    return 'Sorry, this errors happened: '.var_dump($err);
});
```

[Top]: #navigation
[Configuration]: #configuration
[Dispatching]: #dispatching
[Simple Routing]: #simple-routing
[Parameters]: #using-parameters
[Catch-all]: #catch-all-parameters
[Matching]: #route-matching
[Methods]: #matching-any-http-method
[Controllers]: #class-controllers
[Streams]: #routing-streams
[Static]: #routing-static-values
[Forwarding]: #forwarding-routes
[When]: #when-routine-if
[By]: #by-routine-before
[Through]: #through-routine-after
[Controller Splitting]: #controller-splitting
[Conneg]: #content-negotiation
[Basic Auth]: #basic-http-auth
[User-Agent]: #filtering-browsers
[Content-Type]: #input-content-type-input-data
[HTTP Errors]: #http-errors
[RESTful Extras]: #restful-extras
[Anti-Patterns]: #anti-patterns
[Own Routines]: #your-own-routines
[Error Handling]: #error-handling
