<?
class Response_Json extends Response
{
	function __construct($data = array(),$status = 200,array $headers = array())
	{
		$data = json_encode($data);
		$headers['Content-Type'] = 'application/json; charset=utf-8';
		return parent::__construct($data,$status,$headers);
	}
}
