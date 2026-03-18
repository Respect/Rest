<?php

require __DIR__ . '/../vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Respect\Rest\Router;

use function Respect\Rest\emit;

$r3 = new Router('/', new Psr17Factory());

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

emit($r3->handle($request));
