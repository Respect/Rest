<?php

declare(strict_types=1);

namespace Respect\Rest;

use JsonSerializable;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

use function array_values;
use function explode;
use function implode;
use function is_array;
use function is_resource;
use function json_encode;
use function strtolower;
use function trim;

final class Responder
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
    ) {
    }

    public function normalize(mixed $result): ResponseInterface
    {
        if ($result instanceof ResponseInterface) {
            return $result;
        }

        $response = $this->responseFactory->createResponse();

        if (is_resource($result)) {
            return $response->withBody($this->streamFactory->createStreamFromResource($result));
        }

        if (is_array($result) || $result instanceof JsonSerializable) {
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream((string) json_encode($result)));
        }

        return $response->withBody($this->streamFactory->createStream((string) $result));
    }

    /**
     * @param array<string, string> $defaultHeaders
     * @param array<string, true> $appendedHeaderNames
     */
    public function finalize(
        mixed $result,
        ResponseInterface|null $responseDraft,
        array $defaultHeaders,
        array $appendedHeaderNames,
        bool $statusOverridden,
        string $method,
    ): ResponseInterface {
        $response = $this->normalize($result);

        if ($responseDraft !== null) {
            if ($statusOverridden) {
                $response = $response->withStatus($responseDraft->getStatusCode(), $responseDraft->getReasonPhrase());
            }
        }

        foreach ($defaultHeaders as $name => $value) {
            if ($response->hasHeader($name)) {
                continue;
            }

            $response = $response->withHeader($name, $value);
        }

        if ($responseDraft !== null) {
            foreach ($responseDraft->getHeaders() as $name => $values) {
                if (!isset($appendedHeaderNames[strtolower($name)])) {
                    $response = $response->withHeader($name, $values);

                    continue;
                }

                $response = $response->withHeader(
                    $name,
                    $this->mergeHeaderValues(
                        $response->getHeaderLine($name),
                        implode(', ', $values),
                    ),
                );
            }
        }

        if ($method !== 'HEAD') {
            return $response;
        }

        return $response->withBody($this->streamFactory->createStream());
    }

    private function mergeHeaderValues(string $existing, string $appended): string
    {
        $mergedValues = [];
        foreach (explode(',', $existing . ',' . $appended) as $headerValue) {
            $headerValue = trim($headerValue);
            if ($headerValue === '') {
                continue;
            }

            $mergedValues[strtolower($headerValue)] = $headerValue;
        }

        return implode(', ', array_values($mergedValues));
    }
}
