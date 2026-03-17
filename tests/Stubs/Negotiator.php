<?php
declare(strict_types=1);

namespace Respect\Rest\Test\Stubs;

use Nyholm\Psr7\ServerRequest;
use Respect\Rest\Request;
use Respect\Rest\Routines\AbstractCallbackMediator;

class Negotiator extends AbstractCallbackMediator
{
    public $decisionmap = [];
    public $outcome = [];

    public function __construct()
    {
        parent::__construct(['a' => 'is_numeric']);
    }

    protected function identifyRequested(Request $request, array $params): array
    {
        if (is_array($this->decisionmap)) {
            return array_keys($this->decisionmap);
        }

        return [];
    }

    protected function considerProvisions(string $requested): array
    {
        return !empty($this->decisionmap[$requested]) ? $this->decisionmap[$requested] : [];
    }

    protected function notifyApproved(string $requested, string $provided, Request $request, array $params): void
    {
        $this->outcome = [
            'approved' => true,
            'requested' => $requested,
            'provided' => $provided,
        ];
    }

    protected function notifyDeclined(string $requested, string $provided, Request $request, array $params): void
    {
        $this->outcome = [
            'approved' => false,
            'requested' => $requested,
            'provided' => $provided,
        ];
    }

    public function pubIdentifyRequested($request = null, $params = [])
    {
        return $this->identifyRequested(new Request(new ServerRequest('GET', '/')), $params);
    }

    public function pubConsiderProvisions($requested)
    {
        return $this->considerProvisions($requested);
    }

    public function pubNotifyApproved($requested, $provided, $request = null, $params = [])
    {
        $this->notifyApproved($requested, $provided, new Request(new ServerRequest('GET', '/')), $params);
    }

    public function pubNotifyDeclined($requested, $provided, $request = null, $params = [])
    {
        $this->notifyDeclined($requested, $provided, new Request(new ServerRequest('GET', '/')), $params);
    }

    public function pubAuthorize($requested, $provided)
    {
        return $this->authorize($requested, $provided);
    }

    public function getMediated($decisionmap)
    {
        $this->decisionmap = $decisionmap;
        $this->outcome = [];
        return $this->when(new Request(new ServerRequest('GET', '/')), []);
    }
}
