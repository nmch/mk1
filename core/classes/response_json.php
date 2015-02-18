<?

class Response_Json extends Response
{
	function __construct($data = [], $status = 200, array $headers = [])
	{
		$data = json_encode($data);
		if( empty($headers['Content-Type']) ){
			$headers['Content-Type'] = Config::get('response_json.content-type', 'application/json; charset=utf-8');
		}

		return parent::__construct($data, $status, $headers);
	}
}
