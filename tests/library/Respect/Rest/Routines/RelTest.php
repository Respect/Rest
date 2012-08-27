<?php
namespace Respect\Rest\Routines;

use Exception;
use PHPUnit_Framework_TestCase;
use ReflectionFunction;

/** 
 * @covers Respect\Rest\Routines\Rel 
 */
class RelTest extends PHPUnit_Framework_TestCase
{
	public function testSimpleTextRelationPassesThroughData()
	{
		$router = new \Respect\Rest\Router;
		$router->get('/', function() {
			return array();
		})->rel(array(
			'item' => '/foo'
		));
		$response = $router->dispatch('GET', '/')->response();

		$this->assertArrayHasKey(
			'links',
			$response,
			'An array of links should be returned when a rel succeeds'
		);

		$this->assertArrayHasKey(
			'item',
			$response['links'],
			'The links array should contain the related link'
		);

		$this->assertContains(
			'/foo',
			$response['links']['item'],
			'The related link key should contain the specified rel value'
		);
	}

	public function testMultipleTextRelationPassesThroughData()
	{
		$router = new \Respect\Rest\Router;
		$router->get('/', function() {
			return array();
		})->rel(array(
			'item' => array('/foo', '/bar')
		));
		$response = $router->dispatch('GET', '/')->response();

		$this->assertCount(
			2,
			$response['links']['item'],
			'The related link key should contain the exact number of related items'
		);

		$this->assertContains(
			'/foo',
			$response['links']['item'],
			'The related link key should contain the specified rel value'
		);

		$this->assertContains(
			'/bar',
			$response['links']['item'],
			'The related link key should contain the specified rel value'
		);
	}
}