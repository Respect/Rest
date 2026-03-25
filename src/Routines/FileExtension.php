<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\DispatchContext;

use function is_callable;
use function spl_object_id;
use function str_ends_with;
use function strlen;
use function substr;
use function usort;

final class FileExtension extends CallbackList implements
    ProxyableBy,
    ProxyableThrough,
    IgnorableFileExtension
{
    private const string REMAINING_ATTRIBUTE = 'respect.ext.remaining';

    /** @return array<int, string> */
    public function getExtensions(): array
    {
        return $this->getKeys();
    }

    /** @param array<int, mixed> $params */
    public function by(DispatchContext $context, array $params): mixed
    {
        $remaining = (string) $context->request->getAttribute(self::REMAINING_ATTRIBUTE, '');

        if ($remaining === '') {
            return null;
        }

        $keys = $this->getKeys();
        usort($keys, static fn(string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($keys as $ext) {
            if (!str_ends_with($remaining, $ext)) {
                continue;
            }

            $remaining = substr($remaining, 0, -strlen($ext));
            $context->withRequest($context->request->withAttribute(
                self::REMAINING_ATTRIBUTE,
                $remaining,
            ));
            $context->withRequest($context->request->withAttribute(
                $this->negotiatedAttributeKey(),
                $this->getCallback($ext),
            ));

            return null;
        }

        return null;
    }

    /** @param array<int, mixed> $params */
    public function through(DispatchContext $context, array $params): mixed
    {
        $value = $context->request->getAttribute($this->negotiatedAttributeKey());

        return is_callable($value) ? $value : null;
    }

    private function negotiatedAttributeKey(): string
    {
        return 'rest.fileext.' . spl_object_id($this);
    }
}
