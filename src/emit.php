<?php

declare(strict_types=1);

namespace Respect\Rest;

use Psr\Http\Message\ResponseInterface;

use function header;
use function sprintf;

function emit(ResponseInterface $response): void
{
    $statusCode = $response->getStatusCode();
    $reasonPhrase = $response->getReasonPhrase();

    header(sprintf(
        'HTTP/%s %d%s',
        $response->getProtocolVersion(),
        $statusCode,
        $reasonPhrase !== '' ? ' ' . $reasonPhrase : '',
    ), true, $statusCode);

    foreach ($response->getHeaders() as $name => $values) {
        foreach ($values as $value) {
            header($name . ': ' . $value, false);
        }
    }

    echo $response->getBody();
}
