<?php

class Arrobj extends ArrayObject
{
	public function __get($index)
	{
		return property_exists($this, $index) ? $this->$inedx : null;
	}

	public function offsetGet($index)
	{
		return $this->offsetExists($index) ? parent::offsetGet($index) : null;
	}
}
