<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Router;

use function trigger_error;

use const E_USER_WARNING;

/** @covers Respect\Rest\Routes\Error */
final class ErrorTest extends TestCase
{
    /**
     * @covers Respect\Rest\Routes\Error::getReflection
     * @covers Respect\Rest\Routes\Error::runTarget
     * @covers Respect\Rest\Router::errorRoute
     */
    #[RunInSeparateProcess]
    public function testMagicConstuctorCanCreateRoutesToErrors(): void
    {
        $router = new Router('', new Psr17Factory());
        $called = false;
        $phpUnit = $this;
        $router->errorRoute(static function ($err) use (&$called, $phpUnit) {
            $called = true;
            $phpUnit->assertContains(
                'Oops',
                $err[0],
                'The error message should be available in the error route',
            );

            return 'There has been an error.';
        });
        $router->get('/', static function (): void {
            trigger_error('Oops', E_USER_WARNING);
        });
        $resp = $router->dispatch(new ServerRequest('GET', '/'))->response();
        self::assertNotNull($resp);
        $response = (string) $resp->getBody();

        self::assertTrue($called, 'The error route must have been called');

        self::assertEquals(
            'There has been an error.',
            $response,
            'An error should be caught by the router and forwarded',
        );
    }
}
