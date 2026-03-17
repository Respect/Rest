<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunctionAbstract;
use ReflectionParameter;
use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Routines\ParamSynced;
use Respect\Rest\Routines\ProxyableBy;
use Respect\Rest\Routines\ProxyableThrough;
use Respect\Rest\Routines\Routinable;
use Throwable;

use function array_values;
use function assert;
use function explode;
use function implode;
use function is_callable;
use function rawurldecode;
use function rtrim;
use function set_error_handler;
use function strtolower;
use function strtoupper;
use function trim;

/** Internal routing context wrapping a PSR-7 server request */
final class DispatchContext
{
    /** @var array<int, mixed> */
    public array $params = [];

    public AbstractRoute|null $route = null;

    /** @var array<string, string> Headers to apply to the final response (set by routines) */
    public array $responseHeaders = [];

    /** @var array<string, string> Comma-merged headers to append to the final response */
    public array $responseAppendedHeaders = [];

    /** @var array<string, string> Headers to apply only when the response does not already have them */
    public array $responseDefaultHeaders = [];

    /** HTTP status code override set by routines (e.g. 406 for content negotiation failure) */
    public int|null $responseStatus = null;

    public ResponseFactoryInterface|null $responseFactory = null;

    private string $effectiveMethod = '';

    private string $effectivePath = '';

    public function __construct(public ServerRequestInterface $request)
    {
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

    public function overrideMethod(string $method): void
    {
        $this->effectiveMethod = strtoupper($method);
    }

    public function setPath(string $path): void
    {
        $this->effectivePath = $path;
    }

    public function hasPreparedResponse(): bool
    {
        return $this->responseStatus !== null;
    }

    public function clearResponseMeta(): void
    {
        $this->responseStatus = null;
        $this->responseHeaders = [];
        $this->responseAppendedHeaders = [];
        $this->responseDefaultHeaders = [];
    }

    public function setResponseHeader(string $name, string $value): void
    {
        $this->responseHeaders[$name] = $value;
    }

    public function appendResponseHeader(string $name, string $value): void
    {
        $this->responseAppendedHeaders[$name] = $this->mergeHeaderValues(
            $this->responseAppendedHeaders[$name] ?? '',
            $value,
        );
    }

    public function defaultResponseHeader(string $name, string $value): void
    {
        $this->responseDefaultHeaders[$name] ??= $value;
    }

    /** @param array<string, string> $headers */
    public function prepareResponse(int $status, array $headers = []): void
    {
        $this->route = null;
        $this->clearResponseMeta();
        $this->responseStatus = $status;
        foreach ($headers as $name => $value) {
            $this->setResponseHeader($name, $value);
        }
    }

    /** Generates the PSR-7 response from the current route */
    public function response(): ResponseInterface|null
    {
        try {
            if (!$this->route instanceof AbstractRoute) {
                if ($this->responseStatus !== null && $this->responseFactory !== null) {
                    return $this->finalizeResponse(
                        $this->responseFactory->createResponse($this->responseStatus),
                    );
                }

                return null;
            }

            $errorHandler = $this->prepareForErrorForwards();
            $preRoutineResult = $this->processPreRoutines();

            if ($preRoutineResult !== null) {
                if ($preRoutineResult instanceof ResponseInterface) {
                    return $this->finalizeResponse($preRoutineResult);
                }

                if ($preRoutineResult === false) {
                    return $this->finalizeResponse($this->route->wrapResponse(''));
                }

                return $this->finalizeResponse($this->route->wrapResponse($preRoutineResult));
            }

            $rawResult = $this->route->dispatchTarget($this->method(), $this->params, $this);

            if ($rawResult instanceof AbstractRoute) {
                return $this->forward($rawResult);
            }

            $processedResult = $this->processPosRoutines($rawResult);
            $errorResponse = $this->forwardErrors($errorHandler);

            if ($errorResponse !== null) {
                return $errorResponse;
            }

            return $this->finalizeResponse($this->route->wrapResponse($processedResult));
        } catch (Throwable $e) {
            $exceptionResponse = $this->catchExceptions($e);
            if ($exceptionResponse === null) {
                throw $e;
            }

            return $exceptionResponse;
        }
    }

    /** @param array<int, mixed> $params */
    public function routineCall(string $type, string $method, Routinable $routine, array &$params): mixed
    {
        assert($this->route !== null);
        $reflection = $this->route->getTargetReflection($method);

        $callbackParameters = [];

        if (!$routine instanceof ParamSynced) {
            $callbackParameters = $params;
        } elseif ($reflection !== null) {
            foreach ($routine->getParameters() as $parameter) {
                $callbackParameters[] = $this->extractRouteParam(
                    $reflection,
                    $parameter,
                    $params,
                );
            }
        }

        return $routine->{$type}($this, $callbackParameters);
    }

    public function forward(AbstractRoute $route): ResponseInterface|null
    {
        $this->route = $route;

        return $this->response();
    }

    /** @return callable|null The previous error handler, or null */
    protected function prepareForErrorForwards(): callable|null
    {
        assert($this->route !== null);
        foreach ($this->route->sideRoutes as $sideRoute) {
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

    protected function processPreRoutines(): mixed
    {
        assert($this->route !== null);
        foreach ($this->route->routines as $routine) {
            if (!$routine instanceof ProxyableBy) {
                continue;
            }

            $result = $this->routineCall(
                'by',
                $this->method(),
                $routine,
                $this->params,
            );

            if ($result instanceof AbstractRoute) {
                return $this->forward($result);
            }

            if ($result instanceof ResponseInterface) {
                return $result;
            }

            if ($result === false) {
                return false;
            }
        }

        return null;
    }

    /**
     * Processes post-routines on the raw handler result.
     * Routines still receive raw values (arrays, strings, etc.) — not ResponseInterface.
     */
    protected function processPosRoutines(mixed $response): mixed
    {
        $proxyResults = [];
        assert($this->route !== null);

        foreach ($this->route->routines as $routine) {
            if (!($routine instanceof ProxyableThrough)) {
                continue;
            }

            $proxyResults[] = $this->routineCall(
                'through',
                $this->method(),
                $routine,
                $this->params,
            );
        }

        foreach ($proxyResults as $proxyCallback) {
            if (!is_callable($proxyCallback)) {
                continue;
            }

            $response = $proxyCallback($response);
        }

        return $response;
    }

    protected function forwardErrors(callable|null $errorHandler): ResponseInterface|null
    {
        if ($errorHandler !== null) {
            set_error_handler($errorHandler);
        }

        assert($this->route !== null);

        foreach ($this->route->sideRoutes as $sideRoute) {
            if ($sideRoute instanceof Routes\Error && $sideRoute->errors) {
                return $this->forward($sideRoute);
            }
        }

        return null;
    }

    protected function catchExceptions(Throwable $e): ResponseInterface|null
    {
        assert($this->route !== null);
        foreach ($this->route->sideRoutes as $sideRoute) {
            if (!$sideRoute instanceof Routes\Exception) {
                continue;
            }

            $exceptionClass = $e::class;
            if (
                $exceptionClass === $sideRoute->class
                || $sideRoute->class === 'Exception'
                || $sideRoute->class === '\Exception'
            ) {
                $sideRoute->exception = $e;

                return $this->forward($sideRoute);
            }
        }

        return null;
    }

    /** @param array<int, mixed> $params */
    protected function extractRouteParam(
        ReflectionFunctionAbstract $callback,
        ReflectionParameter $routeParam,
        array &$params,
    ): mixed {
        foreach ($callback->getParameters() as $callbackParamReflection) {
            if (
                $callbackParamReflection->getName() === $routeParam->getName()
                && isset($params[$callbackParamReflection->getPosition()])
            ) {
                return $params[$callbackParamReflection->getPosition()];
            }
        }

        if ($routeParam->isDefaultValueAvailable()) {
            return $routeParam->getDefaultValue();
        }

        return null;
    }

    /** Applies pending headers and status code set by routines to a ResponseInterface */
    protected function applyResponseMeta(ResponseInterface $response): ResponseInterface
    {
        if ($this->responseStatus !== null) {
            $response = $response->withStatus($this->responseStatus);
        }

        foreach ($this->responseDefaultHeaders as $name => $value) {
            if ($response->hasHeader($name)) {
                continue;
            }

            $response = $response->withHeader($name, $value);
        }

        foreach ($this->responseAppendedHeaders as $name => $value) {
            $response = $response->withHeader(
                $name,
                $this->mergeHeaderValues($response->getHeaderLine($name), $value),
            );
        }

        foreach ($this->responseHeaders as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    protected function finalizeResponse(ResponseInterface $response): ResponseInterface
    {
        $response = $this->applyResponseMeta($response);

        if ($this->method() !== 'HEAD' || $this->responseFactory === null) {
            return $response;
        }

        return $response->withBody($this->responseFactory->createResponse()->getBody());
    }

    private function mergeHeaderValues(string $existing, string $appended): string
    {
        $mergedValues = [];
        foreach (explode(',', $existing . ',' . $appended) as $headerValue) {
            $headerValue = trim($headerValue);
            if ($headerValue === '') {
                continue;
            }

            $mergedValues[strtolower($headerValue)] = $headerValue;
        }

        return implode(', ', array_values($mergedValues));
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
