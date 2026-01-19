<?php

require '../vendor/autoload.php';

use Respect\Rest\Router;

$r3 = new Router;
$r3->get('/', function () {
    return 'Welcome to Respect/Rest!';
});
