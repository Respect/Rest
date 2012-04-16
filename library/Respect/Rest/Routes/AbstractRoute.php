<?php

namespace Respect\Rest\Routes;

use ReflectionClass;
use Respect\Rest\Request;
use Respect\Rest\Routines\Routinable;
use Respect\Rest\Routines\ProxyableWhen;
use Respect\Rest\Routines\IgnorableFileExtension;
use Respect\Rest\Routines\Unique;
use Respect\Rest\Exception\MethodNotAllowed;

/** Base class for all Routes */
abstract class AbstractRoute
{
    const CATCHALL_IDENTIFIER = '/**';
    const PARAM_IDENTIFIER = '/*';
    const QUOTED_PARAM_IDENTIFIER = '/\*';
    const REGEX_CATCHALL = '(/.*)?';
    const REGEX_SINGLE_PARAM = '/([^/]+)';
    const REGEX_ONE_ENDING_PARAM = '/([^/]+)';
    const REGEX_ONE_OPTIONAL_PARAM = '(?:/([^/]+))?';
    const REGEX_TWO_ENDING_PARAMS = '/([^/]+)/([^/]+)';
    const REGEX_TWO_MIXED_PARAMS = '(?:/([^/]+))?/([^/]+)';
    const REGEX_TWO_OPTIONAL_ENDING_PARAMS = '/([^/]+)(?:/([^/]+))?';
    const REGEX_TWO_OPTIONAL_PARAMS = '(?:/([^/]+))?(?:/([^/]+))?';

    public $method = '';
    public $pattern = '';
    public $regexForMatch = '';
    public $regexForReplace = '';
    public $routines = array();

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

        $this->appendRoutine($routineReflection->newInstanceArgs($arguments));

        return $this;
    }

    /** Appends a pre-built routine to this route */
    public function appendRoutine(Routinable $routine)
    {
        $key = $routine instanceof Unique ? get_class($routine) : spl_object_hash($routine);
        $this->routines[$key] = $routine;
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

    public function matchRoutines(Request $request, &$params=array())
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
                $matchUri = preg_replace('#(\.\w+)*$#', '', $request->uri);

        if (!preg_match($this->regexForMatch, $matchUri, $params))
            return false;

        array_shift($params);
        
        if (false !== stripos($this->pattern, '/**') && false !== stripos(end($params), '/')) {
            $lastParam = array_pop($params);
            $params[] = explode('/', ltrim($lastParam, '/'));
        }

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
        if (strlen($quotedPattern) - strlen(static::REGEX_ONE_ENDING_PARAM)
            === strripos($quotedPattern, static::REGEX_ONE_ENDING_PARAM))
            $quotedPattern = str_replace(
                array(
                    static::REGEX_ONE_ENDING_PARAM,
                    static::REGEX_TWO_ENDING_PARAMS,
                    static::REGEX_TWO_MIXED_PARAMS
                ), array(
                    static::REGEX_ONE_OPTIONAL_PARAM,
                    static::REGEX_TWO_OPTIONAL_ENDING_PARAMS,
                    static::REGEX_TWO_OPTIONAL_PARAMS
                ), $quotedPattern
            );

        return $quotedPattern;
    }

}
