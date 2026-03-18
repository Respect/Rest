<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use Respect\Rest\DispatchContext;
use SplObjectStorage;

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

    /** @var SplObjectStorage<DispatchContext, callable>|null */
    private SplObjectStorage|null $negotiated = null;

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
            $context->request = $context->request->withAttribute(
                self::REMAINING_ATTRIBUTE,
                $remaining,
            );
            $this->remember($context, $this->getCallback($ext));

            return null;
        }

        return null;
    }

    /** @param array<int, mixed> $params */
    public function through(DispatchContext $context, array $params): mixed
    {
        if (!$this->negotiated instanceof SplObjectStorage || !$this->negotiated->offsetExists($context)) {
            return null;
        }

        return $this->negotiated[$context];
    }

    private function remember(DispatchContext $context, callable $callback): void
    {
        if (!$this->negotiated instanceof SplObjectStorage) {
            /** @var SplObjectStorage<DispatchContext, callable> $storage */
            $storage = new SplObjectStorage();
            $this->negotiated = $storage;
        }

        $this->negotiated[$context] = $callback;
    }
}
