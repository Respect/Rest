<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
    /** @const string Identifier for catch-all parameters in a route path */
    const CATCHALL_IDENTIFIER = '/**';
    /** @const string Identifier for normal parameters in a route path */
    const PARAM_IDENTIFIER = '/*';
    /** @const string Quoted version of the normal parameter identifier */
    const QUOTED_PARAM_IDENTIFIER = '/\*';
    /** @const string A regular expression that cathes from a / to the end */
    const REGEX_CATCHALL = '(/.*)?';
    /** @const string A regular expression that cathes one parameter */
    const REGEX_SINGLE_PARAM = '/([^/]+)';
    /** @const string A regular expression that cathes one ending parameter */
    const REGEX_ENDING_PARAM = '#/\(\[\^/\]\+\)#';
    /** @const string A regular expression that cathes one optional parameter */
    const REGEX_OPTIONAL_PARAM = '(?:/([^/]+))?';
    /** @const string A regular expression that identifies invalid parameters */
    const REGEX_INVALID_OPTIONAL_PARAM = '#\(\?\:/\(\[\^/\]\+\)\)\?/#';

    /** @var string The HTTP method for this route (GET, POST, ANY, etc) */
    public $method = '';
    /** @var string The pattern for this route (like /users/*) */
    public $pattern = '';
    /** @var string The generated regex to match the route pattern */
    public $regexForMatch = '';
    /**
     * @var string The generated regex for creating URIs from parameters
     * @see Respect\Rest\AbstractRoute::createURI
     */
    public $regexForReplace = '';
    /**
     * @var array A list of routines appended to this route
     * @see Respect\Rest\Routines\AbstractRoutine
     */
    public $routines = array();
    /**
     * @var array A list of side routes to be used
     * @see Respect\Rest\Routes\AbstractRoute
     */
    public $sideRoutes = array();

    /** @var array A virtualhost applied to this route (deprecated) */
    public $virtualHost = null;

    /**
     * Returns the RelfectionFunctionAbstract object for the passed method
     *
     * @param string $method The HTTP method (GET, POST, etc)
     */
    abstract public function getReflection($method);

    /**
     * Runs the target method/params into this route
     *
     * @param string $method The HTTP method (GET, POST, etc)
     * @param array  $params A list of params to pass to the target
     */
    abstract public function runTarget($method, &$params);

    /**
     * @param string $method  The HTTP method (GET, POST, etc)
     * @param string $pattern The pattern for this route path
     */
    public function __construct($method, $pattern)
    {
        $this->pattern = $pattern;
        $this->method = strtoupper($method);

        list($this->regexForMatch, $this->regexForReplace)
            = $this->createRegexPatterns($pattern);
    }

    /**
     * A magic routine builder and composite appender
     *
     * @param string $method    The HTTP method (GET, POST, etc)
     * @param array  $arguments Arguments to pass to this routine constructor
     * @see   Respect\Rest\Routes\AbstractRoute::appendRoutine
     *
     * @return AbstractRoute The route itselt
     */
    public function __call($method, $arguments)
    {
        $reflection = new ReflectionClass(
            'Respect\\Rest\\Routines\\'.ucfirst($method)
        );

        return $this->appendRoutine($reflection->newInstanceArgs($arguments));
    }

    /**
     * Appends a pre-built routine to this route
     *
     * @param Routinable $routine A routine to be appended
     * @see   Respect\Rest\Routes\AbstractRoute::__call
     *
     * @return AbstractRoute The route itselt
     */
    public function appendRoutine(Routinable $routine)
    {
        $key = $routine instanceof Unique
            ? get_class($routine)
            : spl_object_hash($routine);

        $this->routines[$key] = $routine;

        return $this;
    }

    /**
     * Creates an URI for this route with the passed parameters, replacing
     * them in the declared pattern. /hello/* with ['tom'] returns /hello/tom
     *
     * @param mixed $param1 Some parameter
     * @param mixed $etc    This route accepts as many parameters you can pass
     *
     * @see Respect\Rest\Request::$params
     *
     * @return string the created URI
     */
    public function createUri($param1 = null, $etc = null)
    {
        $params = func_get_args();
        array_unshift($params, $this->regexForReplace);

        $params = preg_replace('#(?<!^)/? *$#', '', $params);

        return rtrim($this->virtualHost, ' /').call_user_func_array('sprintf', $params);
    }

    /**
     * Passes a request through all this routes ProxyableWhen routines
     *
     * @param Request $request The request you want to process
     * @param array   $params  Parameters for the processed request
     * @see   Respect\Rest\Routines\ProxyableWhen
     *
     * @see Respect\Rest\Request::$params
     *
     * @return bool always true \,,/
     */
    public function matchRoutines(Request $request, $params = array())
    {
        foreach ($this->routines as $routine) {
            if ($routine instanceof ProxyableWhen
                    && !$request->routineCall(
                        'when',
                        $request->method,
                        $routine,
                        $params
                    )) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks if a request passes for this route
     *
     * @param Request $request The request you want to process
     * @param array   $params  Parameters for the processed request
     *
     * @see Respect\Rest\Request::$params
     *
     * @return bool as true as xkcd (always true)
     */
    public function match(Request $request, &$params = array())
    {
        $params = array();
        $matchUri = $request->uri;

        foreach ($this->routines as $routine) {
            if ($routine instanceof IgnorableFileExtension) {
                $matchUri = preg_replace(
                    '#(\.[\w\d-_.~\+]+)*$#',
                    '',
                    $request->uri
                );
            }
        }

        if (!preg_match($this->regexForMatch, $matchUri, $params)) {
            return false;
        }

        array_shift($params);

        if (
            false !== stripos($this->pattern, '/**')
            && false !== stripos(end($params), '/')
        ) {
            $lastParam = array_pop($params);
            $params[] = explode('/', ltrim($lastParam, '/'));
        } elseif (
            false !== stripos($this->pattern, '/**') && !isset($params[0])
        ) {
            $params[] = array(); // callback expects a parameter give it
        }

        return true;
    }

    /**
     * This creates a regular expression that matches a route pattern and
     * extracts it's parameters
     *
     * @param string $pattern The pattern for the regex creation
     *
     * @return array A matcher regex and a replacer regex for createUri()
     */
    protected function createRegexPatterns($pattern)
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

        return array($matchRegex, $replacePattern);
    }

    /**
     * Extracts the catch-all parameter from a pattern and modifies the passed
     * parameter to remove that. Yes, we're modifying by reference.
     *
     * @param string $pattern The pattern for the regex creation
     *
     * @return string The catch-all parameter or empty string
     */
    protected function extractCatchAllPattern(&$pattern)
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

    /**
     * Identifies using regular expressions a sequence of parameters in the end
     * of a pattern and make the latest ones optional for the matcher regex
     *
     * @param string $quotedPattern a preg_quoted route pattern
     */
    protected function fixOptionalParams($quotedPattern)
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
            static::REGEX_SINGLE_PARAM.'/',
            $quotedPattern
        );

        return $quotedPattern;
    }
}
