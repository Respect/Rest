<?php

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

	public function through(Request $request, $params)
	{
		$rels = $this;
		return function ($data) use ($rels) {

			if (!isset($data['links'])) {
				$data['links'] = array();
			}

			$data['links'] = array_merge_recursive($data['links'], $rels->getArrayCopy());

			return $data;
		};
	}
}
