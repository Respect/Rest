<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use Respect\Rest\Stream;
use Respect\Rest\Request;
use Respect\Rest\Routines\IgnorableFileExtension;
use Respect\Rest\Routines\ProxyableWhen;
use Respect\Rest\Routines\Routinable;
use Respect\Rest\Routines\Unique;

/**
 * Base class for all Routes
 *
 * @method self accept(mixed ...$args)
 * @method self acceptCharset(mixed ...$args)
 * @method self acceptEncoding(mixed ...$args)
 * @method self acceptLanguage(mixed ...$args)
 * @method self authBasic(mixed ...$args)
 * @method self by(mixed ...$args)
 * @method self contentType(mixed ...$args)
 * @method self lastModified(mixed ...$args)
 * @method self through(mixed ...$args)
 * @method self userAgent(mixed ...$args)
 * @method self when(mixed ...$args)
 */
abstract class AbstractRoute
{
    const string CATCHALL_IDENTIFIER = '/**';
    const string PARAM_IDENTIFIER = '/*';
    const string QUOTED_PARAM_IDENTIFIER = '/\*';
    const string REGEX_CATCHALL = '(/.*)?';
    const string REGEX_SINGLE_PARAM = '/([^/]+)';
    const string REGEX_ENDING_PARAM = '#/\(\[\^/\]\+\)#';
    const string REGEX_OPTIONAL_PARAM = '(?:/([^/]+))?';
    const string REGEX_INVALID_OPTIONAL_PARAM = '#\(\?\:/\(\[\^/\]\+\)\)\?/#';

    public string $method = '';
    public string $pattern = '';
    public string $regexForMatch = '';
    public string $regexForReplace = '';
    /** @var array<string, Routinable> */
    public array $routines = [];
    /** @var array<int, AbstractRoute> */
    public array $sideRoutes = [];
    public ?string $virtualHost = null;
    public ?ResponseFactoryInterface $responseFactory = null;

    abstract public function getReflection(string $method): ?ReflectionFunctionAbstract;

    abstract public function runTarget(string $method, array &$params, Request $request): mixed;

    /**
     * Resolves callback arguments by inspecting parameter types via reflection.
     *
     * PSR-7 typed parameters (ServerRequestInterface, ResponseInterface) are
     * injected automatically. All other parameters consume URL params positionally.
     *
     * @param array<int, mixed> $params URL-extracted parameters
     * @return array<int, mixed> Resolved argument list
     */
    protected function resolveCallbackArguments(
        ReflectionFunctionAbstract $reflection,
        array $params,
        Request $request,
    ): array {
        $refParams = $reflection->getParameters();

        // No declared parameters — pass all URL params through (supports func_get_args())
        if ($refParams === []) {
            return $params;
        }

        $args = [];
        $paramIndex = 0;
        $hasPsrInjection = false;

        foreach ($refParams as $refParam) {
            $type = $refParam->getType();

            if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                if (is_a($typeName, ServerRequestInterface::class, true)) {
                    $args[] = $request->serverRequest;
                    $hasPsrInjection = true;
                    continue;
                }

                if (is_a($typeName, ResponseInterface::class, true)) {
                    $args[] = $this->responseFactory->createResponse();
                    $hasPsrInjection = true;
                    continue;
                }
            }

            $args[] = $params[$paramIndex] ?? ($refParam->isDefaultValueAvailable() ? $refParam->getDefaultValue() : null);
            $paramIndex++;
        }

        // No PSR-7 injection happened — pass params directly (faster, preserves original behavior)
        if (!$hasPsrInjection) {
            return $params;
        }

        return $args;
    }

    /** Wraps a mixed value into a PSR-7 ResponseInterface */
    public function wrapResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        $response = $this->responseFactory->createResponse();

        if (is_resource($result)) {
            return $response->withBody(new Stream($result));
        }

        if (is_array($result) || $result instanceof \JsonSerializable) {
            $response->getBody()->write(json_encode($result));
            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write((string) $result);

        return $response;
    }

    public function __construct(string $method, string $pattern)
    {
        $this->pattern = $pattern;
        $this->method = strtoupper($method);

        [$this->regexForMatch, $this->regexForReplace]
            = $this->createRegexPatterns($pattern);
    }

    /**
     * Magic routine builder — instantiates a Routine by name and appends it.
     *
     * @return static
     */
    public function __call(string $method, array $arguments): static
    {
        $reflection = new ReflectionClass(
            'Respect\\Rest\\Routines\\' . ucfirst($method)
        );

        return $this->appendRoutine($reflection->newInstanceArgs($arguments));
    }

    /** @return static */
    public function appendRoutine(Routinable $routine): static
    {
        $key = $routine instanceof Unique
            ? get_class($routine)
            : spl_object_hash($routine);

        $this->routines[$key] = $routine;

        return $this;
    }

    public function createUri(mixed ...$params): string
    {
        array_unshift($params, $this->regexForReplace);

        $params = preg_replace('#(?<!^)/? *$#', '', $params);

        return rtrim((string) $this->virtualHost, ' /') . sprintf(...$params);
    }

    /** @param array<int, mixed> $params */
    public function matchRoutines(Request $request, array $params = []): bool
    {
        foreach ($this->routines as $routine) {
            if (
                $routine instanceof ProxyableWhen
                && !$request->routineCall(
                    'when',
                    $request->method,
                    $routine,
                    $params
                )
            ) {
                return false;
            }
        }

        return true;
    }

    /** @param array<int, mixed> $params */
    public function match(Request $request, array &$params = []): bool
    {
        $params = [];
        $matchUri = $request->uri;

        foreach ($this->routines as $routine) {
            if ($routine instanceof IgnorableFileExtension) {
                $matchUri = preg_replace(
                    '#(\.[\w\d\-_.~\+]+)*$#',
                    '',
                    $request->uri
                );
            }
        }

        if (!preg_match($this->regexForMatch, $matchUri, $params)) {
            return false;
        }

        array_shift($params);

        $lastParam = end($params);
        if (
            false !== stripos($this->pattern, '/**')
            && is_string($lastParam) && false !== stripos($lastParam, '/')
        ) {
            $lastParam = array_pop($params);
            $params[] = explode('/', ltrim($lastParam, '/'));
        } elseif (
            false !== stripos($this->pattern, '/**') && !isset($params[0])
        ) {
            $params[] = [];
        }

        return true;
    }

    /** @return array{string, string} */
    protected function createRegexPatterns(string $pattern): array
    {
        $extra = $this->extractCatchAllPattern($pattern);

        $matchPattern = str_replace(
            static::QUOTED_PARAM_IDENTIFIER,
            static::REGEX_SINGLE_PARAM,
            preg_quote(rtrim($pattern, ' /')),
            $paramCount
        );

        $pattern = rtrim($pattern);

        $replacePattern = str_replace(
            static::PARAM_IDENTIFIER,
            '/%s',
            $pattern
        );
        $matchPattern = $this->fixOptionalParams($matchPattern);
        $matchRegex = "#^{$matchPattern}{$extra}$#";

        return [$matchRegex, $replacePattern];
    }

    protected function extractCatchAllPattern(string &$pattern): string
    {
        $extra = static::REGEX_CATCHALL;

        if (
            (strlen($pattern) - strlen(static::CATCHALL_IDENTIFIER))
                === strripos($pattern, static::CATCHALL_IDENTIFIER)
        ) {
            $pattern = substr($pattern, 0, -3);
        } else {
            $extra = '';
        }

        $pattern = str_replace(
            static::CATCHALL_IDENTIFIER,
            static::PARAM_IDENTIFIER,
            $pattern
        );

        return $extra;
    }

    protected function fixOptionalParams(string $quotedPattern): string
    {
        if (
            strlen($quotedPattern) - strlen(static::REGEX_SINGLE_PARAM)
            === strripos($quotedPattern, static::REGEX_SINGLE_PARAM)
        ) {
            $quotedPattern = preg_replace(
                static::REGEX_ENDING_PARAM,
                static::REGEX_OPTIONAL_PARAM,
                $quotedPattern
            );
        }

        $quotedPattern = preg_replace(
            static::REGEX_INVALID_OPTIONAL_PARAM,
            static::REGEX_SINGLE_PARAM . '/',
            $quotedPattern
        );

        return $quotedPattern;
    }
}
