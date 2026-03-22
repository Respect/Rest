<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use ReflectionFunctionAbstract;
use Respect\Parameter\Resolver;
use Respect\Rest\DispatchContext;

use function array_merge;
use function base64_decode;
use function explode;
use function stripos;
use function substr;

final class AuthBasic extends AbstractRoutine implements ProxyableBy
{
    private ReflectionFunctionAbstract|null $reflection = null;

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
        $args = $context->resolver()->resolve(
            $this->getCallbackReflection(),
            $allParams,
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
        return Resolver::acceptsType(
            $this->getCallbackReflection(),
            ServerRequestInterface::class,
        );
    }

    private function getCallbackReflection(): ReflectionFunctionAbstract
    {
        return $this->reflection ??= Resolver::reflectCallable($this->getCallback());
    }
}
