<?php

declare(strict_types=1);

namespace Respect\Rest\Routines;

use ArrayObject;
use Respect\Rest\Request;

class Rel extends ArrayObject implements Routinable, ProxyableThrough
{
    public function __construct(array $list)
    {
        $this->setFlags(self::ARRAY_AS_PROPS);
        $this->exchangeArray($list);
    }

    public function extractLinks(mixed $data, mixed $relSpec, bool $deep = true): mixed
    {
        if (is_callable($relSpec)) {
            return $relSpec($data);
        } elseif ($deep && is_array($relSpec)) {
            foreach ($relSpec as &$r) {
                $r = $this->extractLinks($data, $r, false);
            }

            return $relSpec;
        }

        return $relSpec;
    }

    public function through(Request $request, array $params): mixed
    {
        $rels = $this;

        return function ($data) use ($rels) {
            foreach ($rels as &$r) {
                $r = $rels->extractLinks($data, $r);
            }

            if (!isset($data['links'])) {
                $data['links'] = [];
            }

            $data['links'] = array_merge_recursive($data['links'], $rels->getArrayCopy());

            return $data;
        };
    }
}
