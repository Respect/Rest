<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\DispatchContext;

use function array_merge;
use function assert;
use function base64_decode;
use function explode;
use function substr;

final class AuthBasic extends AbstractRoutine implements ProxyableBy
{
    public function __construct(public string $realm, mixed $callback)
    {
        parent::__construct($callback);
    }

    /** @param array<int, mixed> $params */
    public function by(DispatchContext $context, array $params): mixed
    {
        $callbackResponse = false;

        $authorization = $context->request->getHeaderLine('Authorization');

        if ($authorization !== '') {
            $callbackResponse = ($this->callback)(
                ...array_merge(explode(':', base64_decode(substr($authorization, 6))), $params),
            );
        }

        if ($callbackResponse === false) {
            assert($context->route?->responseFactory !== null);
            $response = $context->route->responseFactory->createResponse(401);
            $response = $response->withHeader('WWW-Authenticate', 'Basic realm="' . $this->realm . '"');
            $response->getBody()->write((string) ($this->callback)(null, null));

            return $response;
        }

        return $callbackResponse;
    }
}
