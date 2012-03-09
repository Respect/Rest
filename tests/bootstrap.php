<?php

date_default_timezone_set('UTC');

$pear_path = trim(`pear config-get php_dir`);
set_include_path('../library' 
        . PATH_SEPARATOR . $pear_path 
        . PATH_SEPARATOR . get_include_path());

/**
 * PSR-0 compliant autoloader created by Composer.
 * If this file does not exist, run `composer.phar install` from
 * the project root directory to generate it.
 */
require realpath(dirname(__FILE__) . '/../vendor/.composer/autoload.php');
