<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use ReflectionClass;
use ReflectionFunctionAbstract;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routines\IgnorableFileExtension;
use Respect\Rest\Routines\Routinable;
use Respect\Rest\Routines\Unique;

use function array_map;
use function array_merge;
use function array_pop;
use function array_shift;
use function end;
use function explode;
use function implode;
use function is_string;
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
use function usort;

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
 * @method self fileExtension(mixed ...$args)
 * @method self lastModified(mixed ...$args)
 * @method self through(mixed ...$args)
 * @method self userAgent(mixed ...$args)
 * @method self when(mixed ...$args)
 */
// phpcs:ignore SlevomatCodingStandard.Classes.SuperfluousAbstractClassNaming.SuperfluousPrefix
abstract class AbstractRoute
{
    public const string CATCHALL_IDENTIFIER = '/**';

    public const array CORE_METHODS = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'];
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

    public string|null $basePath = null;

    public function __construct(string $method, public string $pattern = '')
    {
        $this->method = strtoupper($method);

        [$this->regexForMatch, $this->regexForReplace]
            = $this->createRegexPatterns($pattern);
    }

    abstract public function getReflection(string $method): ReflectionFunctionAbstract|null;

    /** @param array<int, mixed> $params */
    abstract public function runTarget(string $method, array &$params, DispatchContext $context): mixed;

    public function getMethodMatchRank(string $method): int|null
    {
        if ($this->method === $method) {
            return 0;
        }

        if ($this->method === 'ANY') {
            return 1;
        }

        if ($this->method === 'GET' && $method === 'HEAD') {
            return 2;
        }

        return null;
    }

    /** @return array<int, string> */
    public function getAllowedMethods(): array
    {
        if ($this->method === 'GET') {
            return ['GET', 'HEAD'];
        }

        if ($this->method === 'ANY') {
            return self::CORE_METHODS;
        }

        return [$this->method];
    }

    public function getTargetMethod(string $method): string
    {
        if ($method === 'HEAD' && $this->method === 'GET') {
            return 'GET';
        }

        return $method;
    }

    /** @param array<int, mixed> $params */
    public function dispatchTarget(string $method, array &$params, DispatchContext $context): mixed
    {
        return $this->runTarget($this->getTargetMethod($method), $params, $context);
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
        return rtrim((string) $this->basePath, ' /') . sprintf($this->regexForReplace, ...$params);
    }

    /** @param array<int, mixed> $params */
    public function match(DispatchContext $context, array &$params = []): bool
    {
        $params = [];
        $matchUri = $context->path();

        $allExtensions = [];
        foreach ($this->routines as $routine) {
            if (!$routine instanceof IgnorableFileExtension) {
                continue;
            }

            $allExtensions = array_merge($allExtensions, $routine->getExtensions());
        }

        if ($allExtensions !== []) {
            usort($allExtensions, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));
            $escaped = array_map(static fn(string $e): string => preg_quote($e, '#'), $allExtensions);
            $extPattern = '#(' . implode('|', $escaped) . ')$#';

            $suffix = '';
            $stripping = true;
            while ($stripping) {
                $stripped = preg_replace($extPattern, '', $matchUri, 1, $count);
                if ($count > 0 && $stripped !== null && $stripped !== $matchUri) {
                    $suffix = substr($matchUri, strlen($stripped)) . $suffix;
                    $matchUri = $stripped;
                } else {
                    $stripping = false;
                }
            }

            if ($suffix !== '') {
                $context->request = $context->request->withAttribute(
                    'respect.ext.remaining',
                    $suffix,
                );
            }
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
