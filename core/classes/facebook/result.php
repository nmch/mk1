<?php

class Facebook_Result
{
	protected $query;
	protected $result;
	protected $code;
	protected $header = [];

	function __construct($result, $query = null, $code = null, $header = [])
	{
		$this->result = $result;
		$this->query  = $query;
		$this->code   = $code;
		$this->header = $header;
		//Log::coredebug($result,$query);
	}

	function get($name = null, $default = null)
	{
		return $name ? Arr::get($this->result, $name, $default) : $this->result;
	}

	function pluck($array_key, $key, $index = null)
	{
		$array = $array_key ? Arr::get($this->result, $array_key, []) : $this->result;

		return Arr::pluck($array, $key, $index);
	}
}