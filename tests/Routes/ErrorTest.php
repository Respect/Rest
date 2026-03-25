<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routes;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Respect\Rest\Router;

use function count;
use function trigger_error;

use const E_USER_WARNING;

/** @covers Respect\Rest\Handlers\ErrorHandler */
final class ErrorTest extends TestCase
{
    /**
     * @covers Respect\Rest\Handlers\ErrorHandler::getReflection
     * @covers Respect\Rest\Handlers\ErrorHandler::runTarget
     * @covers Respect\Rest\Router::onError
     */
    #[RunInSeparateProcess]
    public function testMagicConstuctorCanCreateRoutesToErrors(): void
    {
        $router = new Router('', new Psr17Factory());
        $called = false;
        $phpUnit = $this;
        $router->onError(static function ($err) use (&$called, $phpUnit) {
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

    #[RunInSeparateProcess]
    public function testErrorStateDoesNotLeakBetweenDispatches(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->onError(static fn(array $errors) => 'errors: ' . count($errors));
        $router->get('/warn', static function (): string {
            trigger_error('warning1', E_USER_WARNING);

            return 'warned';
        });
        $router->get('/ok', static fn() => 'clean');

        // First dispatch triggers an error
        $resp1 = $router->handle(new ServerRequest('GET', '/warn'));
        self::assertSame('errors: 1', (string) $resp1->getBody());

        // Second dispatch should not inherit the first request's errors
        $resp2 = $router->handle(new ServerRequest('GET', '/ok'));
        self::assertSame('clean', (string) $resp2->getBody());
    }
}
