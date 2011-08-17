<?php

class Respect_Rest_Demos_Helloworld extends PHPUnit_Framework_TestCase
{

    public function testIndex()
    {
        include '../demos/helloworld/index.php';
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $this->assertEquals('Hi!', (string) $r->dispatch());
    }

}