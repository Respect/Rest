<?php

declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Nyholm\Psr7\ServerRequest;
use Respect\Rest\DispatchContext;
use Respect\Rest\Routines\AbstractCallbackMediator;

use function array_keys;
use function is_array;

class Negotiator extends AbstractCallbackMediator
{
    /** @var array<string, array<int, string>> */
    public array $decisionmap = [];

    /** @var array<string, mixed> */
    public array $outcome = [];

    public function __construct()
    {
        parent::__construct(['a' => 'is_numeric']);
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return array<int, string>
     */
    public function pubIdentifyRequested(DispatchContext|null $context = null, array $params = []): array
    {
        return $this->identifyRequested(new DispatchContext(new ServerRequest('GET', '/')), $params);
    }

    /** @return array<int, string> */
    public function pubConsiderProvisions(string $requested): array
    {
        return $this->considerProvisions($requested);
    }

    /** @param array<int, mixed> $params */
    public function pubNotifyApproved(
        string $requested,
        string $provided,
        DispatchContext|null $context = null,
        array $params = [],
    ): void {
        $this->notifyApproved($requested, $provided, new DispatchContext(new ServerRequest('GET', '/')), $params);
    }

    /** @param array<int, mixed> $params */
    public function pubNotifyDeclined(
        string $requested,
        string $provided,
        DispatchContext|null $context = null,
        array $params = [],
    ): void {
        $this->notifyDeclined($requested, $provided, new DispatchContext(new ServerRequest('GET', '/')), $params);
    }

    public function pubAuthorize(string $requested, string $provided): mixed
    {
        return $this->authorize($requested, $provided);
    }

    /** @param array<string, array<int, string>>|string $decisionmap */
    public function getMediated(array|string $decisionmap): bool
    {
        if (is_array($decisionmap)) {
            $this->decisionmap = $decisionmap;
        } else {
            $this->decisionmap = [];
        }

        $this->outcome = [];

        return (bool) $this->when(new DispatchContext(new ServerRequest('GET', '/')), []);
    }

    /**
     * @param array<int, mixed> $params
     *
     * @return array<int, string>
     */
    protected function identifyRequested(DispatchContext $context, array $params): array
    {
        return array_keys($this->decisionmap);
    }

    /** @return array<int, string> */
    protected function considerProvisions(string $requested): array
    {
        return !empty($this->decisionmap[$requested]) ? $this->decisionmap[$requested] : [];
    }

    /** @param array<int, mixed> $params */
    protected function notifyApproved(
        string $requested,
        string $provided,
        DispatchContext $context,
        array $params,
    ): void {
        $this->outcome = [
            'approved' => true,
            'requested' => $requested,
            'provided' => $provided,
        ];
    }

    /** @param array<int, mixed> $params */
    protected function notifyDeclined(
        string $requested,
        string $provided,
        DispatchContext $context,
        array $params,
    ): void {
        $this->outcome = [
            'approved' => false,
            'requested' => $requested,
            'provided' => $provided,
        ];
    }
}
