<?php

session_start();
set_include_path(realpath('../../library/') . PATH_SEPARATOR . __DIR__ . PATH_SEPARATOR . get_include_path());

spl_autoload_register(require_once 'Respect/Loader.php');


$r = new Respect\Rest\Router('/demos/usuarios/index.php');

$r->get('/', '\\classes\\usuarios');

$r->get('/*','\\classes\\usuarios');

$r->post('/add/','\\classes\\usuarios');


$r->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'])->response();


