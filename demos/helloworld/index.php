<?php

spl_autoload_register(include 'Respect/Loader.php');

$r = new Respect\Rest\Router('/helloworld/index.php');

$r->get('/', function() {
    return 'Hi!';
});