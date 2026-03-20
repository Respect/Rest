<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use Respect\Rest\DispatchContext;
use Respect\Rest\ResolvesCallbackArguments;

use function array_merge;
use function base64_decode;
use function explode;
use function is_a;
use function is_array;
use function stripos;
use function substr;

final class AuthBasic extends AbstractRoutine implements ProxyableBy
{
    use ResolvesCallbackArguments;

    public function __construct(public string $realm, mixed $callback)
    {
        parent::__construct($callback);
    }

    /** @param array<int, mixed> $params */
    public function by(DispatchContext $context, array $params): mixed
    {
        $authorization = $context->request->getHeaderLine('Authorization');
        $hasCredentials = $authorization !== '' && stripos($authorization, 'Basic ') === 0;

        if ($hasCredentials) {
            $credentials = explode(':', base64_decode(substr($authorization, 6)));
        } elseif ($this->callbackAcceptsPsr7()) {
            $credentials = ['', ''];
        } else {
            return $this->unauthorizedResponse($context);
        }

        $allParams = array_merge($credentials, $params);
        $args = $this->resolveCallbackArguments(
            $this->getCallbackReflection(),
            $allParams,
            $context,
        );

        $callbackResponse = ($this->callback)(...$args);

        if ($callbackResponse === false) {
            return $this->unauthorizedResponse($context);
        }

        return $callbackResponse;
    }

    private function unauthorizedResponse(DispatchContext $context): ResponseInterface
    {
        $response = $context->factory->createResponse(401);

        return $response->withHeader('WWW-Authenticate', 'Basic realm="' . $this->realm . '"');
    }

    private function callbackAcceptsPsr7(): bool
    {
        foreach ($this->getCallbackReflection()->getParameters() as $param) {
            $type = $param->getType();

            if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            if (is_a($type->getName(), ServerRequestInterface::class, true)) {
                return true;
            }
        }

        return false;
    }

    private function getCallbackReflection(): ReflectionFunctionAbstract
    {
        $callback = $this->getCallback();

        if (is_array($callback)) {
            return new ReflectionMethod($callback[0], $callback[1]);
        }

        return new ReflectionFunction(Closure::fromCallable($callback));
    }
}
