<?php

namespace Respect\Rest\Routes;

use ReflectionClass;
use Respect\Rest\Request;
use Respect\Rest\Routines\Routinable;
use Respect\Rest\Routines\ProxyableWhen;
use Respect\Rest\Routines\IgnorableFileExtension;
use Respect\Rest\Routines\Unique;

/**
 * Base class for all Routes
 *
 * @method \Respect\Rest\Routes\AbstractRoute abstractAccept()
 * @method \Respect\Rest\Routes\AbstractRoute abstractRoutine()
 * @method \Respect\Rest\Routes\AbstractRoute abstractSyncedRoutine()
 * @method \Respect\Rest\Routes\AbstractRoute acceptCharset()
 * @method \Respect\Rest\Routes\AbstractRoute acceptEncoding()
 * @method \Respect\Rest\Routes\AbstractRoute acceptLanguage()
 * @method \Respect\Rest\Routes\AbstractRoute accept()
 * @method \Respect\Rest\Routes\AbstractRoute authBasic()
 * @method \Respect\Rest\Routes\AbstractRoute by()
 * @method \Respect\Rest\Routes\AbstractRoute contentType()
 * @method \Respect\Rest\Routes\AbstractRoute ignorableFileExtension()
 * @method \Respect\Rest\Routes\AbstractRoute lastModified()
 * @method \Respect\Rest\Routes\AbstractRoute paramSynced()
 * @method \Respect\Rest\Routes\AbstractRoute proxyableBy()
 * @method \Respect\Rest\Routes\AbstractRoute proxyableThrough()
 * @method \Respect\Rest\Routes\AbstractRoute proxyableWhen()
 * @method \Respect\Rest\Routes\AbstractRoute routinable()
 * @method \Respect\Rest\Routes\AbstractRoute through()
 * @method \Respect\Rest\Routes\AbstractRoute unique()
 * @method \Respect\Rest\Routes\AbstractRoute userAgent()
 * @method \Respect\Rest\Routes\AbstractRoute when()
 * ...
 */
abstract class AbstractRoute
{
    const CATCHALL_IDENTIFIER = '/**';
    const PARAM_IDENTIFIER = '/*';
    const QUOTED_PARAM_IDENTIFIER = '/\*';
    const REGEX_CATCHALL = '(/.*)?';
    const REGEX_SINGLE_PARAM = '/([^/]+)';
    const REGEX_ENDING_PARAM = '#/\(\[\^/\]\+\)#';
    const REGEX_OPTIONAL_PARAM = '(?:/([^/]+))?';
    const REGEX_INVALID_OPTIONAL_PARAM = '#\(\?\:/\(\[\^/\]\+\)\)\?/#';

    public $method = '';
    public $pattern = '';
    public $regexForMatch = '';
    public $regexForReplace = '';
    public $routines = array();
    public $sideRoutes = array();

    /** Returns the RelfectionFunctionAbstract object for the passed method */
    abstract public function getReflection($method);

    /** Runs the target method/params into this route */
    abstract public function runTarget($method, &$params);

    public function __construct($method, $pattern)
    {
        $this->pattern = $pattern;
        $this->method = strtoupper($method);
        list($this->regexForMatch, $this->regexForReplace)
            = $this->createRegexPatterns($pattern);
    }

    public function __call($method, $arguments)
    {
        $routineReflection = new ReflectionClass(
                'Respect\\Rest\\Routines\\' . ucfirst($method)
        );

        return $this->appendRoutine($routineReflection->newInstanceArgs($arguments));
    }

    /** Appends a pre-built routine to this route */
    public function appendRoutine(Routinable $routine)
    {
        $key = $routine instanceof Unique ? get_class($routine) : spl_object_hash($routine);
        $this->routines[$key] = $routine;
        return $this;
    }

    /** Creates an URI for this route with the passed parameters */
    public function createUri($param1=null, $etc=null)
    {
        $params = func_get_args();
        array_unshift($params, $this->regexForReplace);
        return call_user_func_array(
                'sprintf', $params
        );
    }

    public function matchRoutines(Request $request, $params=array())
    {
        foreach ($this->routines as $routine)
            if ($routine instanceof ProxyableWhen
                && !$request->routineCall('when', $request->method, $routine, $params))
                return false;

        return true;
    }

    /** Checks if this route matches a request */
    public function match(Request $request, &$params=array())
    {
        $params = array();
        $matchUri = $request->uri;

        foreach ($this->routines as $routine)
            if ($routine instanceof IgnorableFileExtension)
                $matchUri = preg_replace('#(\.[\w\d-_.~\+]+)*$#', '',
                    $request->uri);

        if (!preg_match($this->regexForMatch, $matchUri, $params))
            return false;

        array_shift($params);

        if (false !== stripos($this->pattern, '/**') && false !== stripos(end($params), '/')) {
            $lastParam = array_pop($params);
            $params[] = explode('/', ltrim($lastParam, '/'));
        }
        elseif (false !== stripos($this->pattern, '/**') && !isset($params[0]))
                $params[] = array(); // callback expects a parameter give it

        return true;
    }

    /** Creates the regex from the route patterns */
    protected function createRegexPatterns($pattern)
    {
        $pattern = rtrim($pattern, ' /');
        $extra = $this->extractCatchAllPattern($pattern);
        $matchPattern = str_replace(
            static::QUOTED_PARAM_IDENTIFIER, static::REGEX_SINGLE_PARAM, preg_quote($pattern), $paramCount
        );
        $replacePattern = str_replace(static::PARAM_IDENTIFIER, '/%s', $pattern);
        $matchPattern = $this->fixOptionalParams($matchPattern);
        $matchRegex = "#^{$matchPattern}{$extra}$#";
        return array($matchRegex, $replacePattern);
    }

    /** Extracts the catch-all param from a pattern */
    protected function extractCatchAllPattern(&$pattern)
    {
        $extra = static::REGEX_CATCHALL;

        if ((strlen($pattern) - strlen(static::CATCHALL_IDENTIFIER))
            === strripos($pattern, static::CATCHALL_IDENTIFIER))
            $pattern = substr($pattern, 0, -3);
        else
            $extra = '';

        $pattern = str_replace(
            static::CATCHALL_IDENTIFIER, static::PARAM_IDENTIFIER, $pattern
        );
        return $extra;
    }

    /** Turn sequenced parameters optional */
    protected function fixOptionalParams($quotedPattern)
    {
        if (strlen($quotedPattern) - strlen(static::REGEX_SINGLE_PARAM)
            === strripos($quotedPattern, static::REGEX_SINGLE_PARAM))
            $quotedPattern = preg_replace(
                static::REGEX_ENDING_PARAM,
                static::REGEX_OPTIONAL_PARAM,
                $quotedPattern
            );

        $quotedPattern = preg_replace(
            static::REGEX_INVALID_OPTIONAL_PARAM,
            static::REGEX_SINGLE_PARAM.'/',
            $quotedPattern
        );

        return $quotedPattern;
    }

}
