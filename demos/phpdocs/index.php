<?php

spl_autoload_register(include 'Respect/Loader.php');

$r = new Respect\Rest\Router('/phpdocs/index.php');

$r->get('/', function() {
    header('Location: classes');
});

$r->get('/classes', function() {
    return array(
        'classes'=> get_declared_classes()
    );
});

$r->get('/classes/*', function($className) {
    return array(
        'class' => $className,
        'methods' => get_class_methods($className)
    );
});

$r->always('Accept', array(
    'text/html' => function($data) {
        extract($data);
        include 'templates/main.phtml';
    },
    '.json' => $jsonHandler = function($data) {
        header('Content-type: application/json');
        return json_encode($data);
    },
    'application/json' => $jsonHandler
));

