<?php

namespace Respect\Rest\Routines;

use Respect\Rest\Routes\AbstractRoute;
use Respect\Rest\Request;

class AuthBasic extends AbstractRoutine implements ProxyableBy
{
	public $realm;

	public function __construct($realm, $callback) 
	{
		$this->realm = $realm;
		parent::__construct($callback);
	}

	public function by(Request $request, $params)
	{
		if (isset($_SERVER['HTTP_AUTHORIZATION']))
			return call_user_func_array(
				$this->callback,
				explode(':', base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6))
			));
		elseif (isset($_SERVER['PHP_AUTH_USER']))
			return call_user_func($this->callback, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);

		header('HTTP/1.1 401');
		header("WWW-Authenticate: Basic realm=\"{$this->realm}\"");
		return call_user_func($this->callback, null, null);
	}

}
