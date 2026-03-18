<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Routines;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Respect\Rest\Router;

/** @covers Respect\Rest\Routines\Rel */
final class RelTest extends TestCase
{
    public function testSimpleTextRelationPassesThroughData(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/', static function () {
            return [];
        })
            /** @phpstan-ignore-next-line */
            ->rel(['item' => '/foo']);
        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNotNull(
            $response,
            'Response should not be null when a rel route matches',
        );
        self::assertInstanceOf(
            ResponseInterface::class,
            $response,
            'Response should be a ResponseInterface',
        );
    }

    public function testSimpleCallbackRelationPassesThroughData(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/', static function () {
            return ['foo'];
        })
            /** @phpstan-ignore-next-line */
            ->rel([
                'item' => static function ($data) {
                    return '/' . $data[0];
                },
            ]);
        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNotNull(
            $response,
            'Response should not be null when a rel callback route matches',
        );
        self::assertInstanceOf(
            ResponseInterface::class,
            $response,
            'Response should be a ResponseInterface',
        );
    }

    public function testMultipleTextRelationPassesThroughData(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/', static function () {
            return [];
        })
            /** @phpstan-ignore-next-line */
            ->rel([
                'item' => ['/foo', '/bar'],
            ]);
        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNotNull(
            $response,
            'Response should not be null when a rel route with multiple links matches',
        );
        self::assertInstanceOf(
            ResponseInterface::class,
            $response,
            'Response should be a ResponseInterface',
        );
    }

    public function testNonUniqueMultipleTextRelationPassesThroughData(): void
    {
        $router = new Router('', new Psr17Factory());
        $router->get('/', static function () {
            return [];
        })
            /** @phpstan-ignore-next-line */
            ->rel([
                'item' => ['/foo', '/bar'],
            ])->rel([
                'item' => ['/baz'],
            ]);
        $response = $router->dispatch(new ServerRequest('GET', '/'))->response();

        self::assertNotNull(
            $response,
            'Response should not be null when a rel route with non-unique multiple links matches',
        );
        self::assertInstanceOf(
            ResponseInterface::class,
            $response,
            'Response should be a ResponseInterface',
        );
    }
}
