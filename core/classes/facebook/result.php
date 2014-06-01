<?php
class Facebook_Result
{
	protected $query;
	protected $result;
	protected $code;
	protected $header = array();
			
	function __construct($result,$query = NULL,$code = NULL,$header = array())
	{
		$this->result = $result;
		$this->query = $query;
		$this->code = $code;
		$this->header = $header;
		//Log::coredebug($result,$query);
	}
	
	function get($name = NULL,$default = NULL)
	{
		return $name ? Arr::get($this->result,$name,$default) : $this->result;
	}
	function pluck($array_key, $key, $index = null)
	{
		$array = $array_key ? Arr::get($this->result,$array_key,array()) : $this->result;
		return Arr::pluck($array,$key,$index);
	}
}