<?php

class Response_Json extends Response
{
	function __construct($data = [], $status = 200, array $headers = [])
	{
		$data = ($data === null) ? null : Mk::json_encode($data);
		$r    = parent::__construct($data, $status, $headers);
		
		$this->set_header('Content-Type', Config::get('response_json.content-type', 'application/json; charset=utf-8'));
		
		return $r;
	}
}
