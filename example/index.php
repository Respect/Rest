<?php

require '../vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Rest\HttpFactories;
use Respect\Rest\Router;

$factory = new Psr17Factory();
$r3 = new Router(new HttpFactories($factory, $factory));

$r3->get('/', function () {
    return 'Welcome to Respect/Rest!';
});

// PSR-7 injection: type-hint ServerRequestInterface or ResponseInterface
// in your callbacks to receive them automatically.
$r3->get('/hello/*', function (string $name, ServerRequestInterface $request) {
    $accept = $request->getHeaderLine('Accept');
    return "Hello, {$name}! (Accept: {$accept})";
});

$r3->get('/download/*', function (string $file, ResponseInterface $response) {
    $response->getBody()->write("Contents of {$file}");
    return $response->withHeader('Content-Type', 'application/octet-stream');
});

$request = new ServerRequest(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $_SERVER['REQUEST_URI'] ?? '/',
);
$response = $r3->dispatch($request)->response();

if ($response !== null) {
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header("$name: $value", false);
        }
    }
    echo (string) $response->getBody();
}
