<?php
namespace Respect\Rest\Routines;

use Exception;
use PHPUnit_Framework_TestCase;
use ReflectionFunction;

/** 
 * @covers Respect\Rest\Routines\When
 */
class WhenTest2 extends PHPUnit_Framework_TestCase
{
	public function testRoutineWhenShouldBlockRouteFromMatchIfTheCallbackReturnIsFalse()
	{
		$router = new \Respect\Rest\Router;
		$router->get('/', function () {
			return 'Oh yeah!';
		});
		$router->get('/', function () {
			return 'Oh noes!';
		})->when(function () {
			return false;
		});
		$response = $router->dispatch('GET', '/')->response();

		$this->assertEquals(
			'Oh yeah!',
			$response,
			'For two identical routes, a failed When routine should not dispatch, the other one should'
		);

		$this->assertNotEquals(
			'Oh noes!',
			$response,
			'For two identical routes, a failed When routine should not dispatch, the other one should'
		);
	}

	public function testRoutineWhenShouldConsiderSyncedCallbackParameters()
	{
		$phpUnit = $this;
		$router = new \Respect\Rest\Router;
		$router->get('/speakers/*', function ($speakerName) {
			return "Hello $speakerName";
		})->when(function ($speakerName) use ($phpUnit) {
			$phpUnit->assertEquals('alganet', $speakerName);
			return strlen($speakerName) >= 3;
		});
		$response = $router->dispatch('GET', '/speakers/alganet')->response();

		$this->assertEquals(
			'Hello alganet',
			$response,
			'This When routine accepts parameters longer than 3 chars, alganet is, so it should pass'
		);
	}
}