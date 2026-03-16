<?php

require '../vendor/autoload.php';

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use Respect\Rest\Router;

$factory = new Psr17Factory();
$r3 = new Router($factory);
$r3->get('/', function () {
    return 'Welcome to Respect/Rest!';
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
