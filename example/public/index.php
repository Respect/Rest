<?php

require_once __DIR__.'/../../vendor/autoload.php';

use Respect\Rest\Router;

$r3 = new Router;

$r3->any('/**', function ($url) {
    return 'Welcome to Respect/Rest the url you want is: /'.implode('/', $url);
});

