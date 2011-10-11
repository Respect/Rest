<?php

set_include_path(realpath('../../library/') . PATH_SEPARATOR . __DIR__ . PATH_SEPARATOR . get_include_path());

spl_autoload_register(include 'Respect/Loader.php');


$db = new PDO('sqlite:/tmp/users.db');

@$db->query('CREATE TABLE
            IF NOT EXISTS
            users (id int auto_increment, name varchar(15),
            PRIMARY KEY (id))')->execute();

$method = (isset($_REQUEST['_method']))? $_REQUEST['_method'] : $_SERVER['REQUEST_METHOD'];

$r = new Respect\Rest\Router('/users/index.php');

$r->get('/users', '\\entity\\users');
$r->post('/users','\\entity\\users');
$r->delete('/users/*','\\entity\\users');

$r->dispatch($method, $_SERVER['REQUEST_URI'])->response();


