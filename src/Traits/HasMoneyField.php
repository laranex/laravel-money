<?php

namespace Laranex\LaravelMoney\Traits;

trait HasMoneyField
{
	public function getMoneyField($field)
	{
		return $this->money[$field];
	}

	public function setMoneyField($field, $value)
	{
		$this->money[$field] = $value;
	}
}
