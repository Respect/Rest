<?php
namespace Respect\Rest\Routines;

use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionFunction;

/** 
 * @covers Respect\Rest\Routines\Rel 
 */
class RelTest extends TestCase
{
	public function testSimpleTextRelationPassesThroughData()
	{
		$router = new \Respect\Rest\Router;
		$router->get('/', function() {
			return [];
		})->rel([
			'item' => '/foo'
		]);
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

		$this->assertStringContainsString(
			'/foo',
			$response['links']['item'],
			'The related link key should contain the specified rel value'
		);
	}
	public function testSimpleCallbackRelationPassesThroughData()
	{
		$router = new \Respect\Rest\Router;
		$router->get('/', function() {
			return ['foo'];
		})->rel([
			'item' => function ($data) {
				return "/".$data[0];
			}
		]);
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

		$this->assertStringContainsString(
			'/foo',
			$response['links']['item'],
			'The related link key should contain the specified rel value'
		);
	}

	public function testMultipleTextRelationPassesThroughData()
	{
		$router = new \Respect\Rest\Router;
		$router->get('/', function() {
			return [];
		})->rel([
			'item' => ['/foo', '/bar']
		]);
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
	public function testNonUniqueMultipleTextRelationPassesThroughData()
	{
		$router = new \Respect\Rest\Router;
		$router->get('/', function() {
			return [];
		})->rel([
			'item' => ['/foo', '/bar']
		])->rel([
			'item' => ['/baz']
		]);
		$response = $router->dispatch('GET', '/')->response();

		$this->assertCount(
			3,
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

		$this->assertContains(
			'/baz',
			$response['links']['item'],
			'The related link key should contain the specified rel value'
		);
	}
}