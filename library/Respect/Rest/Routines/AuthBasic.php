<?php
namespace Respect\Rest\Routines;

class AuthBasic extends AbstractRoutine implements ProxyableBy
{
	public function by(\Respect\Rest\Request $request, $params)
	{
		if(isset($_SERVER['HTTP_AUTHORIZATION'])) {
			$httpAuthorization     = base64_decode(substr($_SERVER['HTTP_AUTHORIZATION'], 6));
			list($user, $passw)    = explode(':', $httpAuthorization);
			call_user_func($this->callback, $user, $passw);
		} elseif (isset($_SERVER['PHP_AUTH_USER'])) {
			call_user_func($this->callback, $_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
		}
		header('HTTP/1.1 401');
	}

}
