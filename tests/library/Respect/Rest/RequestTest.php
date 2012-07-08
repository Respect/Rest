<?php
namespace Respect\Rest;

use Exception;
use PHPUnit_Framework_TestCase;

class RequestTest extends PHPUnit_Framework_TestCase
{
    public function testIsPossibleToConstructFromEnv()
    {
        $_SERVER['REQUEST_URI'] = '/users';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        $request = new Request;

        $this->assertEquals('/users', $request->uri);
        $this->assertEquals('GET', $request->method);
    }

    public function testIsPossibleToConstructWithCustomUri()
    {
        $_SERVER['REQUEST_URI'] = '/documents';

        $request = new Request('PATCH');

        $this->assertEquals('/documents', $request->uri);
        $this->assertEquals('PATCH', $request->method);
    }

    public function testIsPossibleToConstructWithCustomMethod()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $request = new Request(null, '/images');

        $this->assertEquals('/images', $request->uri);
        $this->assertEquals('POST', $request->method);
    }

    public function testWhenConstructingThePathShouldBePopulatedFromAbsoluteUri()
    {
        $_SERVER['REQUEST_URI'] = 'http://google.com/search?q=foo';

        $request = new Request('GET');

        $this->assertEquals('/search', $request->uri);

        //TODO change ->uri to ->path, populate other parse_url keys
        //TODO same behavior for env vars and constructor params regarding parse_url
    }
}