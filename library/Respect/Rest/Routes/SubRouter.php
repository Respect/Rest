<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Respect\Rest\Routes;

use ReflectionMethod;
use InvalidArgumentException;
use Respect\Rest\Router;
use Respect\Rest\Request;

class SubRouter extends AbstractRoute
{
    protected $uri = null;
    protected $instance = null;
    /** @var ReflectionMethod */
    protected $reflection;

    public function __construct($method, $pattern, $instance)
    {
        $this->instance = $instance;
        parent::__construct($method, $pattern);
    }

    public function getReflection($method)
    {
        if (empty($this->reflection)) {
            $this->reflection = new ReflectionMethod(
                $this,
                'runTarget'
            );
        }

        return $this->reflection;
    }

    public function match(Request $request, &$params = array()){
       #Store the request
       $this->uri = $request->uri;
       $params=array();
       if(preg_match("#^".$this->pattern."#", $request->uri)){
          $this->uri = preg_replace("#^".$this->pattern."#", '', $this->uri);
          return true;
       }
       return false;
    }

    public function runTarget($method, &$params)
    {
        if (!$this->instance instanceof Router) {
            throw new InvalidArgumentException('Route target must be an instance of Respect\Rest\Router');
        }

        return (string)$this->instance->run(new Request($method, $this->uri));
    }
}
