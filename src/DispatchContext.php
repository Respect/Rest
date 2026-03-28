<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Respect\Parameter\Resolver;
use Respect\Rest\Handlers\ErrorHandler;
use Respect\Rest\Handlers\ExceptionHandler;
use Respect\Rest\Handlers\StatusHandler;
use Respect\Rest\Routes\AbstractRoute;
use Throwable;

use function in_array;
use function is_a;
use function preg_quote;
use function preg_replace;
use function rawurldecode;
use function rtrim;
use function set_error_handler;
use function sprintf;
use function strtolower;
use function strtoupper;

/** Internal routing context wrapping a PSR-7 server request */
final class DispatchContext implements ContainerInterface
{
    /** @var array<int, mixed> */
    public private(set) array $params = [];

    public private(set) AbstractRoute|null $route = null;

    /** @var array<string, string> Headers to apply only when the response does not already have them */
    public private(set) array $defaultResponseHeaders = [];

    private Responder|null $responder = null;

    private ResponseInterface|null $responseDraft = null;

    /** @var array<string, true> */
    private array $appendedResponseHeaderNames = [];

    private bool $hasPreparedResponse = false;

    private bool $hasStatusOverride = false;

    private string $effectiveMethod;

    private string $effectivePath;

    private Resolver|null $resolver = null;

    /** @param array<int, AbstractRoute> $handlers */
    public function __construct(
        public private(set) ServerRequestInterface $request,
        public private(set) ResponseFactoryInterface&StreamFactoryInterface $factory,
        private RoutinePipeline $routinePipeline = new RoutinePipeline(),
        private array $handlers = [],
        string $basePath = '',
    ) {
        $path = rtrim(rawurldecode($request->getUri()->getPath()), ' /');
        if ($basePath !== '') {
            $path = preg_replace(
                '#^' . preg_quote($basePath, '#') . '#',
                '',
                $path,
            ) ?? $path;
        }

        $this->effectivePath = $path;
        $this->effectiveMethod = strtoupper($request->getMethod());
        $this->resetHandlerState();
    }

    public function method(): string
    {
        return $this->effectiveMethod;
    }

    public function path(): string
    {
        return $this->effectivePath;
    }

    public function hasPreparedResponse(): bool
    {
        return $this->hasPreparedResponse;
    }

    public function clearResponseMeta(): void
    {
        $this->responseDraft = null;
        $this->defaultResponseHeaders = [];
        $this->appendedResponseHeaderNames = [];
        $this->hasPreparedResponse = false;
        $this->hasStatusOverride = false;
    }

    public function setResponseHeader(string $name, string $value): void
    {
        unset($this->appendedResponseHeaderNames[strtolower($name)]);
        $this->responseDraft = $this->ensureResponseDraft()->withHeader($name, $value);
    }

    public function appendResponseHeader(string $name, string $value): void
    {
        $this->appendedResponseHeaderNames[strtolower($name)] = true;
        $this->responseDraft = $this->ensureResponseDraft()->withAddedHeader($name, $value);
    }

    public function defaultResponseHeader(string $name, string $value): void
    {
        $this->defaultResponseHeaders[$name] ??= $value;
    }

    /** @param array<string, string> $headers */
    public function prepareResponse(int $status, array $headers = []): void
    {
        $this->route = null;
        $this->clearResponseMeta();
        $this->hasPreparedResponse = true;
        $this->hasStatusOverride = true;
        $this->responseDraft = $this->ensureResponseDraft()->withStatus($status);
        foreach ($headers as $name => $value) {
            $this->setResponseHeader($name, $value);
        }
    }

    /** @param array<int, mixed> $params */
    public function configureRoute(AbstractRoute $route, array $params = []): void
    {
        $this->route = $route;
        $this->params = $params;
    }

    /** Generates the PSR-7 response from the current route */
    public function response(): ResponseInterface|null
    {
        if (!$this->route instanceof AbstractRoute) {
            if ($this->responseDraft !== null) {
                $statusResponse = $this->forwardToStatusRoute($this->responseDraft);
                if ($statusResponse !== null) {
                    return $statusResponse;
                }

                return $this->finalizeResponse($this->responseDraft);
            }

            return null;
        }

        $route = $this->route;
        $isHandler = in_array($route, $this->handlers, true);
        $previousErrorHandler = $isHandler ? null : $this->installErrorHandler();

        try {
            $preRoutineResult = $this->routinePipeline->processBy($this, $route);

            if ($preRoutineResult instanceof AbstractRoute) {
                return $this->forward($preRoutineResult);
            }

            if ($preRoutineResult instanceof ResponseInterface) {
                return $this->finalizeResponse($preRoutineResult);
            }

            if ($preRoutineResult === false) {
                return $this->finalizeResponse('');
            }

            $rawResult = $route->dispatchTarget($this->method(), $this->params, $this);

            if ($rawResult instanceof AbstractRoute) {
                return $this->forward($rawResult);
            }

            $processedResult = $this->routinePipeline->processThrough($this, $route, $rawResult);

            if (!$isHandler) {
                $errorResponse = $this->forwardCollectedErrors();
                if ($errorResponse !== null) {
                    return $errorResponse;
                }
            }

            return $this->finalizeResponse($processedResult);
        } catch (Throwable $e) {
            if (!$isHandler) {
                $exceptionResponse = $this->catchExceptions($e);
                if ($exceptionResponse !== null) {
                    return $exceptionResponse;
                }
            }

            throw $e;
        } finally {
            if ($previousErrorHandler !== null) {
                set_error_handler($previousErrorHandler);
            }
        }
    }

    public function withRequest(ServerRequestInterface $request): void
    {
        $this->request = $request;
    }

    public function setResponder(Responder $responder): void
    {
        $this->responder = $responder;
    }

    public function forward(AbstractRoute $route): ResponseInterface|null
    {
        $this->route = $route;

        return $this->response();
    }

    public function resolver(): Resolver
    {
        return $this->resolver ??= new Resolver($this);
    }

    public function has(string $id): bool
    {
        return is_a($id, ServerRequestInterface::class, true)
            || is_a($id, ResponseInterface::class, true);
    }

    public function get(string $id): mixed
    {
        if (is_a($id, ServerRequestInterface::class, true)) {
            return $this->request;
        }

        if (is_a($id, ResponseInterface::class, true)) {
            return $this->ensureResponseDraft();
        }

        throw new NotFoundException(sprintf('No entry found for "%s"', $id));
    }

    private function resetHandlerState(): void
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof ErrorHandler) {
                $handler->clearErrors();
            } elseif ($handler instanceof ExceptionHandler) {
                $handler->clearException();
            }
        }
    }

    /**
     * Safe only when requests are guaranteed not to overlap within the same PHP process
     * (for example: PHP-FPM, Swoole workers, FrankenPHP workers, or ReactPHP when
     * request handling/dispatch is strictly serialized). Not safe for coroutine- or
     * event-loop-concurrent request handling within a single PHP process, since
     * set_error_handler is global state.
     *
     * @return callable|null The previous error handler, or null if no ErrorHandler is registered
     */
    private function installErrorHandler(): callable|null
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof ErrorHandler) {
                return set_error_handler(
                    static function (
                        int $errno,
                        string $errstr,
                        string $errfile = '',
                        int $errline = 0,
                    ) use ($handler): bool {
                        $handler->addError($errno, $errstr, $errfile, $errline);

                        return true;
                    },
                );
            }
        }

        return null;
    }

    private function forwardCollectedErrors(): ResponseInterface|null
    {
        foreach ($this->handlers as $handler) {
            if ($handler instanceof ErrorHandler && $handler->hasErrors()) {
                return $this->forward($handler);
            }
        }

        return null;
    }

    private function catchExceptions(Throwable $e): ResponseInterface|null
    {
        foreach ($this->handlers as $handler) {
            if (!$handler instanceof ExceptionHandler) {
                continue;
            }

            if ($handler->matches($e)) {
                $handler->capture($e);

                return $this->forward($handler);
            }
        }

        return null;
    }

    private function forwardToStatusRoute(ResponseInterface $preparedResponse): ResponseInterface|null
    {
        $statusCode = $preparedResponse->getStatusCode();

        foreach ($this->handlers as $handler) {
            if (
                $handler instanceof StatusHandler
                && ($handler->statusCode === $statusCode || $handler->statusCode === null)
            ) {
                $this->hasStatusOverride = true;

                // Run routine negotiation (e.g. Accept) before forwarding,
                // since the normal route-selection phase was skipped
                $this->routinePipeline->matches($this, $handler, $this->params);

                $result = $this->forward($handler);

                // Preserve the original status code on the forwarded response
                return $result?->withStatus($statusCode);
            }
        }

        return null;
    }

    private function finalizeResponse(mixed $response): ResponseInterface
    {
        return $this->responder()->finalize(
            $response,
            $this->responseDraft,
            $this->defaultResponseHeaders,
            $this->appendedResponseHeaderNames,
            $this->hasStatusOverride,
            $this->method(),
        );
    }

    private function responder(): Responder
    {
        return $this->responder ??= new Responder($this->factory);
    }

    private function ensureResponseDraft(): ResponseInterface
    {
        return $this->responseDraft ??= $this->factory->createResponse();
    }

    public function __toString(): string
    {
        $response = $this->response();

        if ($response === null) {
            return '';
        }

        return (string) $response->getBody();
    }
}
