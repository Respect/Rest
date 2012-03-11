<?php
namespace Respect\Rest\Exception;
use \BadMethodCallException;
use \Exception;

/**
 * Exception for HTTP 405 error code.
 */
class MethodNotAllowed extends BadMethodCallException
{
	
}