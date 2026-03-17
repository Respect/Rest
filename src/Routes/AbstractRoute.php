<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use JsonSerializable;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionClass;
use ReflectionFunctionAbstract;
use ReflectionNamedType;
use Respect\Rest\Request;
use Respect\Rest\Routines\IgnorableFileExtension;
use Respect\Rest\Routines\ProxyableWhen;
use Respect\Rest\Routines\Routinable;
use Respect\Rest\Routines\Unique;
use Respect\Rest\Stream;

use function array_pop;
use function array_shift;
use function assert;
use function end;
use function explode;
use function is_a;
use function is_array;
use function is_resource;
use function is_string;
use function json_encode;
use function ltrim;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function rtrim;
use function spl_object_hash;
use function sprintf;
use function str_replace;
use function stripos;
use function strlen;
use function strripos;
use function strtoupper;
use function substr;
use function ucfirst;

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
// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousAbstractClassNaming.SuperfluousPrefix
abstract class AbstractRoute
{
    public const string CATCHALL_IDENTIFIER = '/**';
    public const string PARAM_IDENTIFIER = '/*';
    public const string QUOTED_PARAM_IDENTIFIER = '/\*';
    public const string REGEX_CATCHALL = '(/.*)?';
    public const string REGEX_SINGLE_PARAM = '/([^/]+)';
    public const string REGEX_ENDING_PARAM = '#/\(\[\^/\]\+\)#';
    public const string REGEX_OPTIONAL_PARAM = '(?:/([^/]+))?';
    public const string REGEX_INVALID_OPTIONAL_PARAM = '#\(\?\:/\(\[\^/\]\+\)\)\?/#';

    public string $method = '';

    public string $regexForMatch = '';

    public string $regexForReplace = '';

    /** @var array<string, Routinable> */
    public array $routines = [];

    /** @var array<int, AbstractRoute> */
    public array $sideRoutes = [];

    public string|null $virtualHost = null;

    public ResponseFactoryInterface|null $responseFactory = null;

    public function __construct(string $method, public string $pattern = '')
    {
        $this->method = strtoupper($method);

        [$this->regexForMatch, $this->regexForReplace]
            = $this->createRegexPatterns($pattern);
    }

    abstract public function getReflection(string $method): ReflectionFunctionAbstract|null;

    /** @param array<int, mixed> $params */
    abstract public function runTarget(string $method, array &$params, Request $request): mixed;

    /** Wraps a mixed value into a PSR-7 ResponseInterface */
    public function wrapResponse(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        assert($this->responseFactory !== null);
        $response = $this->responseFactory->createResponse();

        if (is_resource($result)) {
            return $response->withBody(new Stream($result));
        }

        if (is_array($result) || $result instanceof JsonSerializable) {
            $response->getBody()->write((string) json_encode($result));

            return $response->withHeader('Content-Type', 'application/json');
        }

        $response->getBody()->write((string) $result);

        return $response;
    }

    /** @return static */
    public function appendRoutine(Routinable $routine): static
    {
        $key = $routine instanceof Unique ? $routine::class : spl_object_hash($routine);

        $this->routines[$key] = $routine;

        return $this;
    }

    public function createUri(mixed ...$params): string
    {
        $params = preg_replace('#(?<!^)/? *$#', '', $params);

        // phpcs:ignore SlevomatCodingStandard.PHP.OptimizedFunctionsWithoutUnpacking.UnpackingUsed
        return rtrim((string) $this->virtualHost, ' /') . sprintf($this->regexForReplace, ...$params);
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
                    $params,
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
            if (!($routine instanceof IgnorableFileExtension)) {
                continue;
            }

            $matchUri = preg_replace(
                '#(\.[\w\d\-_.~\+]+)*$#',
                '',
                $request->uri,
            ) ?? $request->uri;
        }

        if (!preg_match($this->regexForMatch, $matchUri, $params)) {
            return false;
        }

        array_shift($params);

        $lastParam = end($params);
        if (
            stripos($this->pattern, '/**') !== false
            && is_string($lastParam) && stripos($lastParam, '/') !== false
        ) {
            $lastParam = (string) array_pop($params);
            $params[] = explode('/', ltrim($lastParam, '/'));
        } elseif (
            stripos($this->pattern, '/**') !== false && !isset($params[0])
        ) {
            $params[] = [];
        }

        return true;
    }

    /**
     * Resolves callback arguments by inspecting parameter types via reflection.
     *
     * PSR-7 typed parameters (ServerRequestInterface, ResponseInterface) are
     * injected automatically. All other parameters consume URL params positionally.
     *
     * @param array<int, mixed> $params URL-extracted parameters
     *
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

                if (is_a($typeName, ResponseInterface::class, true) && $this->responseFactory !== null) {
                    $args[] = $this->responseFactory->createResponse();
                    $hasPsrInjection = true;
                    continue;
                }
            }

            $default = $refParam->isDefaultValueAvailable() ? $refParam->getDefaultValue() : null;
            $args[] = $params[$paramIndex] ?? $default;
            $paramIndex++;
        }

        // No PSR-7 injection happened — pass params directly (faster, preserves original behavior)
        if (!$hasPsrInjection) {
            return $params;
        }

        return $args;
    }

    /** @return array{string, string} */
    protected function createRegexPatterns(string $pattern): array
    {
        $extra = $this->extractCatchAllPattern($pattern);

        $matchPattern = str_replace(
            self::QUOTED_PARAM_IDENTIFIER,
            self::REGEX_SINGLE_PARAM,
            preg_quote(rtrim($pattern, ' /')),
            $paramCount,
        );

        $pattern = rtrim($pattern);

        $replacePattern = str_replace(
            self::PARAM_IDENTIFIER,
            '/%s',
            $pattern,
        );
        $matchPattern = $this->fixOptionalParams($matchPattern);
        $matchRegex = '#^' . $matchPattern . $extra . '$#';

        return [$matchRegex, $replacePattern];
    }

    protected function extractCatchAllPattern(string &$pattern): string
    {
        $extra = self::REGEX_CATCHALL;

        if (
            strlen($pattern) - strlen(self::CATCHALL_IDENTIFIER) === strripos($pattern, self::CATCHALL_IDENTIFIER)
        ) {
            $pattern = substr($pattern, 0, -3);
        } else {
            $extra = '';
        }

        $pattern = str_replace(
            self::CATCHALL_IDENTIFIER,
            self::PARAM_IDENTIFIER,
            $pattern,
        );

        return $extra;
    }

    protected function fixOptionalParams(string $quotedPattern): string
    {
        $lastPos = strripos($quotedPattern, self::REGEX_SINGLE_PARAM);
        if (
            strlen($quotedPattern) - strlen(self::REGEX_SINGLE_PARAM) === $lastPos
        ) {
            $quotedPattern = preg_replace(
                self::REGEX_ENDING_PARAM,
                self::REGEX_OPTIONAL_PARAM,
                $quotedPattern,
            ) ?? $quotedPattern;
        }

        return preg_replace(
            self::REGEX_INVALID_OPTIONAL_PARAM,
            self::REGEX_SINGLE_PARAM . '/',
            $quotedPattern,
        ) ?? $quotedPattern;
    }

    /**
     * Magic routine builder — instantiates a Routine by name and appends it.
     *
     * @param array<int, mixed> $arguments
     *
     * @return static
     */
    public function __call(string $method, array $arguments): static
    {
        /** @var class-string<Routinable> $className */
        $className = 'Respect\\Rest\\Routines\\' . ucfirst($method);
        $reflection = new ReflectionClass($className);
        // phpcs:ignore SlevomatCodingStandard.PHP.RequireExplicitAssertion
        /** @var Routinable $instance */
        $instance = $reflection->newInstanceArgs($arguments);

        return $this->appendRoutine($instance);
    }
}
