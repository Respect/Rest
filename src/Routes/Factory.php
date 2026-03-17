<?php

declare(strict_types=1);

namespace Respect\Rest\Routes;

use InvalidArgumentException;
use Respect\Rest\Request;
use Respect\Rest\Routable;

final class Factory extends ControllerRoute
{
    protected object|null $instance = null;

    public function __construct(
        string $method,
        string $pattern,
        public string $class = '',
        /** @var callable */
        public $factory = null,
    ) {
        $this->reflectionTarget = $class;

        parent::__construct($method, $pattern);
    }

    /** @param array<int, mixed> $params */
    public function runTarget(string $method, array &$params, Request $request): mixed
    {
        if ($this->instance === null) {
            $this->instance = ($this->factory)($method, $params);
        }

        if (!$this->instance instanceof Routable) {
            throw new InvalidArgumentException(
                'Routed classes must implement the Respect\\Rest\\Routable interface',
            );
        }

        return $this->invokeTarget($this->instance, $method, $params, $request);
    }
}
