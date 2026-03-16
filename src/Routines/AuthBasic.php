<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\Request;

class AuthBasic extends AbstractRoutine implements ProxyableBy
{
    public string $realm;

    public function __construct(string $realm, mixed $callback)
    {
        $this->realm = $realm;
        parent::__construct($callback);
    }

    public function by(Request $request, array $params): mixed
    {
        $callbackResponse = false;

        $authorization = $request->serverRequest->getHeaderLine('Authorization');

        if ($authorization !== '') {
            $callbackResponse = ($this->callback)(
                ...array_merge(explode(':', base64_decode(substr($authorization, 6))), $params)
            );
        }

        if ($callbackResponse === false) {
            $response = $request->route->responseFactory->createResponse(401);
            $response = $response->withHeader('WWW-Authenticate', "Basic realm=\"{$this->realm}\"");
            $response->getBody()->write((string) ($this->callback)(null, null));

            return $response;
        }

        return $callbackResponse;
    }
}
