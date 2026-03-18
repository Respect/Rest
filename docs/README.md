# Feature Guide

## Configuration

The Router takes two arguments: a base path and a PSR-17 factory that implements both
`ResponseFactoryInterface` and `StreamFactoryInterface` (most PSR-17 implementations
such as `nyholm/psr7` do this in a single class):
```php
use Respect\Rest\Router;

$r3 = new Router('/', $factory);
```

The base path tells the Router where your application is mounted. Use `'/'` for the
domain root (`http://example.com/`).

For a subfolder:
```php
$r3 = new Router('/myapp', $factory);
```

This will instruct the Router to work from `http://example.com/myapp/`.
Trailing slashes are normalized automatically, so `/myapp/` and `/myapp` are equivalent.

## Dispatching

After declaring routes, dispatch a PSR-7 `ServerRequestInterface` to get a response:
```php
use function Respect\Rest\emit;

$response = $r3->handle($request);
emit($response);
```

The `handle()` method returns a PSR-7 `ResponseInterface`. The `emit()` helper sends the
status line, headers, and body to the client.

If you need access to the dispatch context (matched route, parameters, etc.):
```php
$context = $r3->dispatch($request);
$response = $context->response();
```

## Simple Routing

The Hello World route goes something like this:
```php
$r3->get('/', function() {
    return 'Hello World';
});
```

Hitting `http://localhost/` will print "Hello World" in the browser. You can declare
as many routes as you want:
```php
$r3->get('/hello', function() {
    return 'Hello from Path';
});
```

## Using Parameters

You can declare routes that receive parameters from the URL. For this, every parameter
is a `/*` on the route path:
```php
$r3->get('/users/*', function($screenName) {
    return "User {$screenName}";
});
```

Accessing `http://localhost/users/alganet` will print "User alganet".

Multiple parameters can be defined:
```php
$r3->get('/users/*/lists/*', function($user, $list) {
    return "List {$list} from user {$user}.";
});
```

Last parameters on the route path are optional by default, so declaring just
a `->get('/posts/*')` will match `http://localhost/posts/` without any
parameter. You can declare a second `->get('/posts')`, or treat the missing
parameter yourself by making them `null`able:
```php
$r3->get('/posts/*/*/*', function($year, $month=null, $day=null) {
    /** list posts, month and day are optional */
});
```

 1. This will match `/posts/2010/10/10`, `/posts/2011/01` and `/posts/2010`
 2. Optional parameters are allowed only at the end of the route path. This
    does not allow optional parameters: `/posts/*/*/*/comments/*`

## Catch-all Parameters

Sometimes you need to catch an undefined number of parameters. You can use
routes with catch-all parameters like this:
```php
$r3->get('/users/*/documents/**', function($user, $documentPath) {
    return readfile(PATH_STORAGE . implode('/', $documentPath));
});
```

 1. The above sample will match `/users/alganet/documents/foo/bar/baz/anything`.
    The `$user` parameter will receive `alganet` and `$documentPath` will
    receive an array filled with `[foo, bar, baz, anything]`.
 2. Catch-all parameters are defined by a double asterisk `/**`.
 3. Catch-all parameters must appear only at the end of the path. Double
    asterisks in any other position will be converted to single asterisks.
 4. Catch-all parameters will match **after** any other route that matches
    the same pattern.

## Route Matching

Things can become complex quickly. We have simple routes, routes with parameters, optional
parameters and catch-all parameters. A simple rule to keep in mind is that Respect\Rest
matches the routes from the most specific to the most generic.

  * Routes with the most slashes `/` are more specific and will be matched first.
  * Routes with parameters are less specific than routes without parameters.
  * Routes with multiple parameters are even less specific than a route with one parameter.
  * Routes with catch-all parameters are the least specific and will be matched last.

Summing up: slashes and asterisks place your route at the top of the priority list to match first.

Respect\Rest sorts routes automatically, but it is highly recommended to declare routes
from the most specific to the most generic. This will improve performance and
maintainability of the code.

## Multiple Routes

You may want to have multiple routes perform the same action. Pluralization is the most
common reason for this:
```php
$r3->get(['/user/*', '/users/*'], function($userName) {
    return 'Hello ' . $userName;
});
```

## Matching any HTTP Method

Sometimes you need to use a router to proxy requests or map requests to a class. By using
the magic method `any`, you can match any HTTP method:
```php
$r3->any('/users/*', function($userName) {
    /** do anything */
});
```

 1. Any HTTP method will match this same route.
 2. You can figure out the method from the PSR-7 request (see PSR-7 Injection below).

## Class Controllers

The `any` method is extremely useful to bind classes to controllers, one of Respect\Rest's
most powerful features:
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
  4. Classes must implement the interface `Respect\Rest\Routable` for safety reasons.

Passing constructor arguments to the class is also possible:
```php
$r3->any('/images/*', 'ImageController', [$myImageHandler, $myDb]);
```

You can also pass a pre-instantiated object:
```php
$r3->any('/downloads/*', $myDownloadManager);
```

  1. The `$myDownloadManager` will be used as the controller directly.
  2. This instance is reused by Respect\Rest.

### Factory Routes

You can use a factory or DI container to build the controller class:
```php
$r3->any('/downloads/*', 'MyControllerClass', function($method, $params) use ($container) {
    return $container->get(MyControllerClass::class);
});
```

  1. The third argument is any *callable*. It receives the HTTP method and route parameters.
  2. The returned instance must implement `Routable`.
  3. This instance is reused by Respect\Rest.

## PSR-7 Injection

Type-hint `ServerRequestInterface` or `ResponseInterface` in your callbacks to receive
them automatically:
```php
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

$r3->get('/hello/*', function(string $name, ServerRequestInterface $request) {
    $accept = $request->getHeaderLine('Accept');
    return "Hello, {$name}! (Accept: {$accept})";
});

$r3->get('/download/*', function(string $file, ResponseInterface $response) {
    $response->getBody()->write("Contents of {$file}");
    return $response->withHeader('Content-Type', 'application/octet-stream');
});
```

  1. Parameters are matched by type, not position. Mix them freely with route parameters.
  2. This works with callback routes and class controller methods alike.

## PSR-15 Integration

The Router implements both `RequestHandlerInterface` and `MiddlewareInterface` from PSR-15.

As a **handler** (standalone):
```php
$response = $r3->handle($request);
```

As **middleware** in a pipeline, the Router delegates to the next handler when no route
matches (404). Other errors like 405 (wrong method) are handled by the Router itself:
```php
$response = $r3->process($request, $nextHandler);
```

## Routing Streams

Sometimes you need to route users to streams. The Router doesn't need to buffer
large files in memory before serving them:
```php
$r3->get('/images/*/hi-res', function($imageName) {
    return fopen("/path/to/hi/images/{$imageName}.jpg", 'r');
});
```

This will send the file directly to the client without keeping it in memory.

**CAUTION:** Validate user input parameters before using them in file paths.
The sample above is for demonstration only.

## Routing Static Values

No surprises here, you can make a route return a plain string:
```php
$r3->get('/greetings', 'Hello!');
```

## Forwarding Routes

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

The sample above will forward internally when a user is not privileged,
redirecting to the normal users route.

## When Routine (if)

Respect\Rest uses a different approach to validate route parameters:
```php
$r3->get('/documents/*', function($documentId) {
    /** do something */
})->when(function($documentId) {
    return is_numeric($documentId) && $documentId > 0;
});
```

  1. This will match the route only if the callback on *when* is matched.
  2. The `$documentId` param must have the same name in the action and the
     condition (but does not need to appear in the same order).
  3. You can specify more than one parameter per condition callback.
  4. You can chain conditions: `when($cb1)->when($cb2)->when($etc)`
  5. Conditions will also sync with parameters on bound classes and instance methods.

This makes it possible to validate parameters using any custom routine and
not just data types such as `int` or `string`.

We highly recommend that you use a strong validation library. Consider
[Respect\Validation](http://github.com/Respect/Validation):
```php
$r3->get('/images/*/hi-res', function($imageName) {
    return fopen("/path/to/hi/images/{$imageName}.jpg", 'r');
})->when(function($imageName) {
    return V::alphanum(".")->length(5, 155)
            ->noWhitespace()->validate($imageName);
});
```

## By Routine (before)

Sometimes you need to run something before a route does its job. This is
useful for logging, authentication and similar purposes:
```php
$r3->get('/artists/*/albums/*', function($artistName, $albumName) {
    /** do something */
})->by(function($albumName) use ($myLogger) {
    $myLogger->logAlbumVisit($albumName);
});
```

  1. This will execute the callback defined with *by* before the route action.
  2. Parameters are synced by name, not by order, like with `when`.
  3. You can specify more than one parameter per proxy callback.
  4. You can chain proxies: `by($cb1)->by($cb2)->by($etc)`
  5. A `return false` from a proxy will stop the execution of any following proxies
     as well as the route action.

If your By routine returns `false`, then the route method/function will not be
processed. If you return an instance of another route, an internal forward
will be performed.

## Through Routine (after)

Similar to `->by`, but runs after the route did its job. In the sample
below we're showing something similar to invalidating a cache after
saving some new information:
```php
$r3->post('/artists/*/albums/*', function($artistName, $albumName) {
    /** save some artist info */
})->through(function($artistName, $albumName) use($myCache) {
    $myCache->clear($artistName, $albumName);
});
```

  1. `by` proxies will be executed before the route action, `through` proxies
     will be executed after.
  2. You are free to use them separately or in tandem.
  3. `through` can also receive parameters by name.

When processing something after the route has run, it's often desirable to process
its output as well. This can be achieved with a nested closure:
```php
$r3->any('/settings', 'SettingsController')->through(function() {
    return function($data) {
        if (isset($data['admin_user'])) {
            unset($data['admin_user']);
        }
        return $data;
    };
});
```

The sample above removes sensitive keys from a settings controller before
outputting the result.

## Controller Splitting

When using routines you are encouraged to separate the controller logic into components.
You can reuse them:
```php
$logRoutine = function(ServerRequestInterface $request) use ($myLogger) {
    $myLogger->logVisit($request->getUri()->getPath());
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
verify them all automatically by name.

## Content Negotiation

Respect\Rest supports four distinct types of Accept header content-negotiation:
Mimetype, Encoding, Language and Charset:
```php
$r3->get('/about', function() {
    return ['v' => 2.0];
})->acceptLanguage([
    'en' => function($data) { return ["Version" => $data['v']]; },
    'pt' => function($data) { return ["Versão"  => $data['v']]; },
])->accept([
    'text/html' => function($data) {
        $k = array_key_first($data);
        return "<strong>{$k}</strong>: {$data[$k]}";
    },
    'application/json' => 'json_encode',
]);
```

As in every routine, conneg routines are executed in the same order in which
you appended them to the route. You can also use `->always` to apply this
routine to every route on the Router.

Please note that when returning streams, conneg routines are also called.
You may take advantage of this when processing streams:
```php
$r3->get('/text/*', function($filename) {
    return fopen('data/' . $filename, 'r+');
})->acceptEncoding([
    'deflate' => function($stream) {
        stream_filter_append($stream, 'zlib.deflate', STREAM_FILTER_READ);
        return $stream; /** now deflated on demand */
    },
]);
```

When applying conneg routines to multiple routes that can return streams you
should check for `is_resource()` before doing anything.

## Basic HTTP Auth

Support for Basic HTTP Authentication is already implemented as a routine:
```php
$r3->get('/home', 'HomeController')->authBasic('My Realm', function($user, $pass) {
    return $user === 'admin' && $pass === 'p4ss';
});
```

You'll receive a username and password provided by the user, and you just need
to return true or false. True means that the user could be authenticated.

Respect\Rest will handle the authentication flow, sending the appropriate
headers when unauthenticated. You can also return another route, which will
act as an internal forward (see the section on forwarding above).

## Filtering Browsers

Below is an illustrative sample of how to handle requests from mobile devices:
```php
$r3->get('/videos/*', 'VideosController')->userAgent([
    'iphone|android' => function() {
        return false; /** do not process the route */
    },
]);
```

You can pass several items in the array. The array key is a regular expression
matcher without delimiters.

## Input Content-Type

By default, HTML forms send POST data as `multipart/form-data`, but API clients
may send any other format. PUT requests often send other mime types. You can pre-process
this data before doing anything:
```php
$r3->post('/timeline', function(ServerRequestInterface $request) {
    return $request->getParsedBody();
})->contentType([
    'application/json' => function($input) {
        return json_decode($input, true);
    },
    'text/xml' => function($input) {
        return simplexml_load_string($input);
    },
]);
```

The parsed content is stored in the request's `parsedBody` attribute, accessible
via `$request->getParsedBody()` inside your route callback.

## HTTP Errors

Respect\Rest handles the following errors by default:

  * 404, when no matching route paths are found.
  * 401, when the client sends an unauthenticated request to a route using `authBasic` routine.
  * 405, when a matching path is found but the method isn't specified.
  * 400, when a `when` validation fails.
  * 406, when the route path and method matches but content-negotiation doesn't.
  * 415, when the request Content-Type doesn't match any `contentType` handler.

## RESTful Extras

  * A HEAD request automatically works sending all GET headers without body. You can override
    this behavior declaring custom `head` routes.
  * An OPTIONS request to `*` or any route path returns the `Allow` headers properly.
  * When returning 405, `Allow` headers are also set properly.

## Your Own Routines

Routines are classes in the `Respect\Rest\Routines` namespace, but you can add your
own routines by instance using:
```php
$r3->get('/greetings', 'Hello World')->appendRoutine(new MyRoutine);
```

In the sample above, `MyRoutine` is a user-provided routine declared as a class and
appended to the route. Custom routines have the option of several different interfaces
which can be implemented:

  * `IgnorableFileExtension` - Instructs the router to ignore the file extension in requests.
  * `ParamSynced` - Syncs parameters with the route function/method.
  * `ProxyableBy` - Instructs the router to run method `by()` before the route.
  * `ProxyableThrough` - Instructs the router to run method `through()` after the route.
  * `ProxyableWhen` - Instructs the router to run method `when()` to validate the route match.
  * `Unique` - Makes this routine be replaced, not appended, if more than one is declared for
    the same type.

You can use any combination of the above but also need to implement the `Routinable` interface.

## Error Handling

Respect\Rest provides two special ways to handle errors. The first one is using exception
routes:

```php
$r3->exceptionRoute('InvalidArgumentException', function (InvalidArgumentException $e) {
    return 'Sorry, this error happened: ' . $e->getMessage();
});
```

Whenever an uncaught exception appears on any route, it will be caught and forwarded to
this side route. Similarly, there is a route for PHP errors:

```php
$r3->errorRoute(function (array $err) {
    return 'Sorry, these errors happened: ' . var_export($err, true);
});
```

***

See also:

- [Contributing](../CONTRIBUTING.md)
- [Installation](INSTALL.md)
- [License](../LICENSE.md)
