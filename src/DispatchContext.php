<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Respect\Parameter\Resolver;
use Respect\Rest\Routes\AbstractRoute;
use Throwable;

use function is_a;
use function rawurldecode;
use function rtrim;
use function set_error_handler;
use function strtolower;
use function strtoupper;

/** Internal routing context wrapping a PSR-7 server request */
final class DispatchContext implements ContainerInterface
{
    /** @var array<int, mixed> */
    public array $params = [];

    public AbstractRoute|null $route = null;

    /** @var array<string, string> Headers to apply only when the response does not already have them */
    public array $defaultResponseHeaders = [];

    private RoutinePipeline|null $routinePipeline = null;

    private Responder|null $responder = null;

    private ResponseInterface|null $responseDraft = null;

    /** @var array<string, true> */
    private array $appendedResponseHeaderNames = [];

    private bool $hasPreparedResponse = false;

    private bool $hasStatusOverride = false;

    private string $effectiveMethod = '';

    private string $effectivePath = '';

    /** @var array<int, AbstractRoute> */
    private array $sideRoutes = [];

    private Resolver|null $resolver = null;

    public function __construct(
        public ServerRequestInterface $request,
        public ResponseFactoryInterface&StreamFactoryInterface $factory,
    ) {
        $this->effectivePath = rtrim(rawurldecode($request->getUri()->getPath()), ' /');
        $this->effectiveMethod = strtoupper($request->getMethod());
    }

    public function method(): string
    {
        return $this->effectiveMethod;
    }

    public function path(): string
    {
        return $this->effectivePath;
    }

    public function setPath(string $path): void
    {
        $this->effectivePath = $path;
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

        try {
            $errorHandler = $this->prepareForErrorForwards($route);
            $preRoutineResult = $this->routinePipeline()->processBy($this, $route);

            if ($preRoutineResult !== null) {
                if ($preRoutineResult instanceof AbstractRoute) {
                    return $this->forward($preRoutineResult);
                }

                if ($preRoutineResult instanceof ResponseInterface) {
                    return $this->finalizeResponse($preRoutineResult);
                }

                if ($preRoutineResult === false) {
                    return $this->finalizeResponse('');
                }
            }

            $rawResult = $route->dispatchTarget($this->method(), $this->params, $this);

            if ($rawResult instanceof AbstractRoute) {
                return $this->forward($rawResult);
            }

            $processedResult = $this->routinePipeline()->processThrough($this, $route, $rawResult);
            $errorResponse = $this->forwardErrors($errorHandler, $route);

            if ($errorResponse !== null) {
                return $errorResponse;
            }

            return $this->finalizeResponse($processedResult);
        } catch (Throwable $e) {
            $exceptionResponse = $this->catchExceptions($e, $route);
            if ($exceptionResponse === null) {
                throw $e;
            }

            return $exceptionResponse;
        }
    }

    public function forward(AbstractRoute $route): ResponseInterface|null
    {
        $this->route = $route;

        return $this->response();
    }

    public function setRoutinePipeline(RoutinePipeline $routinePipeline): void
    {
        $this->routinePipeline = $routinePipeline;
    }

    /** @param array<int, AbstractRoute> $sideRoutes */
    public function setSideRoutes(array $sideRoutes): void
    {
        $this->sideRoutes = $sideRoutes;
    }

    public function setResponder(Responder $responder): void
    {
        $this->responder = $responder;
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
            return $this->factory->createResponse();
        }

        return null;
    }

    /** @return callable|null The previous error handler, or null */
    protected function prepareForErrorForwards(AbstractRoute $route): callable|null
    {
        foreach ($route->sideRoutes as $sideRoute) {
            if ($sideRoute instanceof Routes\Error) {
                return set_error_handler(
                    static function (
                        int $errno,
                        string $errstr,
                        string $errfile = '',
                        int $errline = 0,
                    ) use ($sideRoute): bool {
                        $sideRoute->errors[] = [$errno, $errstr, $errfile, $errline];

                        return true;
                    },
                );
            }
        }

        return null;
    }

    protected function forwardErrors(callable|null $errorHandler, AbstractRoute $route): ResponseInterface|null
    {
        if ($errorHandler !== null) {
            set_error_handler($errorHandler);
        }

        foreach ($route->sideRoutes as $sideRoute) {
            if ($sideRoute instanceof Routes\Error && $sideRoute->errors) {
                return $this->forward($sideRoute);
            }
        }

        return null;
    }

    protected function catchExceptions(Throwable $e, AbstractRoute $route): ResponseInterface|null
    {
        foreach ($route->sideRoutes as $sideRoute) {
            if (!$sideRoute instanceof Routes\Exception) {
                continue;
            }

            if (is_a($e, $sideRoute->class)) {
                $sideRoute->exception = $e;

                return $this->forward($sideRoute);
            }
        }

        return null;
    }

    protected function forwardToStatusRoute(ResponseInterface $preparedResponse): ResponseInterface|null
    {
        $statusCode = $preparedResponse->getStatusCode();

        foreach ($this->sideRoutes as $sideRoute) {
            if (
                $sideRoute instanceof Routes\Status
                && ($sideRoute->statusCode === $statusCode || $sideRoute->statusCode === null)
            ) {
                $this->hasStatusOverride = true;

                // Run routine negotiation (e.g. Accept) before forwarding,
                // since the normal route-selection phase was skipped
                $this->routinePipeline()->matches($this, $sideRoute, $this->params);

                $result = $this->forward($sideRoute);

                // Preserve the original status code on the forwarded response
                return $result?->withStatus($statusCode);
            }
        }

        return null;
    }

    protected function finalizeResponse(mixed $response): ResponseInterface
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

    private function routinePipeline(): RoutinePipeline
    {
        return $this->routinePipeline ??= new RoutinePipeline();
    }

    private function responder(): Responder
    {
        if ($this->responder !== null) {
            return $this->responder;
        }

        return $this->responder = new Responder($this->factory);
    }

    private function ensureResponseDraft(): ResponseInterface
    {
        if ($this->responseDraft !== null) {
            return $this->responseDraft;
        }

        return $this->responseDraft = $this->factory->createResponse();
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
