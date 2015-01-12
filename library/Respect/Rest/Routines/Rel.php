<?php
/*
 * This file is part of the Respect\Rest package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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

    public function extractLinks($data, $relSpec, $deep = true)
    {
        if (is_callable($relSpec)) {
            return call_user_func($relSpec, $data);
        } elseif ($deep && is_array($relSpec)) {
            foreach ($relSpec as &$r) {
                $r = $this->extractLinks($data, $r, false);
            }

            return $relSpec;
        }

        return $relSpec;
    }

    public function through(Request $request, $params)
    {
        $rels = $this;

        return function ($data) use ($rels) {
            foreach ($rels as &$r) {
                $r = $rels->extractLinks($data, $r);
            }

            if (!isset($data['links'])) {
                $data['links'] = array();
            }

            $data['links'] = array_merge_recursive($data['links'], $rels->getArrayCopy());

            return $data;
        };
    }
}
