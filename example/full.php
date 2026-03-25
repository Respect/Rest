<?php

/**
 * Comprehensive Respect\Rest feature showcase.
 *
 * Run from project root: php -S localhost:8000 example/full.php
 *
 * Try these URLs:
 *   GET  /                          → Welcome page
 *   GET  /hello/world               → Parameterized route
 *   GET  /posts/2024                → Optional params (year only)
 *   GET  /posts/2024/03/18          → Optional params (year/month/day)
 *   GET  /files/docs/readme/txt     → Catch-all params
 *   GET  /article/42                → Class controller
 *   GET  /json                      → Content negotiation (use Accept: application/json)
 *   GET  /secret                    → Basic auth (admin / p4ss)
 *   GET  /validated/123             → When routine (valid)
 *   GET  /validated/abc             → When routine (400)
 *   GET  /boom                      → Exception route
 *   GET  /status                    → Static value
 *   GET  /time                      → PSR-7 injection
 *   GET  /data/users.json          → File extension (JSON)
 *   GET  /data/users.html          → File extension (HTML)
 */

require __DIR__ . '/../vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Rest\Routable;
use Respect\Rest\Router;

use function Respect\Rest\emit;

// --- Setup ---

$r3 = new Router('/', new Psr17Factory());

// --- Simple routes ---

$r3->get('/', function () {
    return 'Welcome to the Respect\Rest full example!';
});

$r3->get('/status', 'All systems operational.');

// --- Parameters ---

$r3->get('/hello/*', function (string $name) {
    return "Hello, {$name}!";
});

// Optional parameters
$r3->get('/posts/*/*/*', function (string $year, ?string $month = null, ?string $day = null) {
    $parts = [$year];
    if ($month !== null) {
        $parts[] = $month;
    }
    if ($day !== null) {
        $parts[] = $day;
    }
    return 'Posts for: ' . implode('-', $parts);
});

// Catch-all parameters
$r3->get('/files/**', function (array $segments) {
    return 'File path: ' . implode('/', $segments);
});

// --- PSR-7 Injection ---

$r3->get('/time', function (ServerRequestInterface $request) {
    $tz = $request->getQueryParams()['tz'] ?? 'UTC';
    return 'Server time: ' . date('Y-m-d H:i:s') . " (requested tz: {$tz})";
});

// --- Class Controller ---

class ArticleController implements Routable
{
    public function get(string $id): string
    {
        return "Article #{$id} (GET)";
    }

    public function post(string $id): string
    {
        return "Article #{$id} updated (POST)";
    }

    public function delete(string $id): string
    {
        return "Article #{$id} deleted (DELETE)";
    }
}

$r3->any('/article/*', 'ArticleController');

// --- Factory Route ---

class WidgetController implements Routable
{
    public function __construct(private string $prefix) {}

    public function get(string $id): string
    {
        return "{$this->prefix}: Widget #{$id}";
    }
}

$r3->any('/widget/*', 'WidgetController', function () {
    return new WidgetController('Factory-built');
});

// --- When Routine (validation) ---

$r3->get('/validated/*', function (string $id) {
    return "Validated ID: {$id}";
})->when(function (string $id) {
    return is_numeric($id) && (int) $id > 0;
});

// --- By Routine (before) ---

$r3->get('/logged/*', function (string $name) {
    return "Hello, {$name}!";
})->by(function (string $name) {
    // In a real app, you'd log to a file or service
    error_log("Route accessed with name: {$name}");
});

// --- Through Routine (after) ---

$r3->get('/wrapped/*', function (string $name) {
    return ['greeting' => "Hello, {$name}!"];
})->through(function () {
    return function (array $data) {
        return json_encode($data);
    };
});

// --- File Extensions ---

$r3->get('/data/*', function (string $resource) {
    return ['resource' => $resource, 'items' => ['a', 'b', 'c']];
})->fileExtension([
    '.json' => 'json_encode',
    '.html' => function (array $data) {
        $name = htmlspecialchars($data['resource']);
        $items = array_map('htmlspecialchars', $data['items']);

        return "<h1>{$name}</h1><ul><li>" . implode('</li><li>', $items) . '</li></ul>';
    },
]);

// --- Content Negotiation ---

$r3->get('/json', function () {
    return ['message' => 'Hello', 'version' => 2];
})->accept([
    'application/json' => function ($data) {
        return json_encode($data);
    },
    'text/html' => function ($data) {
        return "<p>{$data['message']} v{$data['version']}</p>";
    },
    'text/plain' => function ($data) {
        return "{$data['message']} v{$data['version']}";
    },
]);

// --- Basic HTTP Auth ---

$r3->get('/secret', function () {
    return 'You are authenticated! Welcome to the secret area.';
})->authBasic('Secret Area', function (string $user, string $pass) {
    return $user === 'admin' && $pass === 'p4ss';
});

// --- Error Handling ---

$r3->get('/boom', function () {
    throw new RuntimeException('Something went wrong!');
});

$r3->onException('RuntimeException', function (RuntimeException $e) {
    return 'Caught exception: ' . $e->getMessage();
});

$r3->onError(function (array $err) {
    return 'Error occurred: ' . ($err[0]['message'] ?? 'unknown');
});

// --- Dispatch ---

$request = new ServerRequest(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/',
    getallheaders() ?: [],
);

emit($r3->handle($request));
