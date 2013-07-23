<?
class FacebookException extends MkException {
	protected $result;
	protected $request_params;
	
	function __construct($result,$request_params = array())
	{
		$this->result = $result;
		$this->request_params = $request_params;

		$code = isset($result['error_code']) ? $result['error_code'] : 0;

		if(isset($result['error_description'])) {
			// OAuth 2.0 Draft 10 style
			$msg = $result['error_description'];
		} else if(isset($result['error']) && is_array($result['error'])) {
			// OAuth 2.0 Draft 00 style
			$msg = $result['error']['message'];
		} else if(isset($result['error_msg'])) {
			// Rest server style
			$msg = $result['error_msg'];
		} else {
			$msg = 'Unknown Error. Check getResult()';
		}

		parent::__construct($msg, $code);
	}
	
	function type()
	{
		return Arr::get($this->result,'error.type');
	}
	
	function result()
	{
		return $this->result;
	}
	function params($key = NULL)
	{
		if($key)
			return Arr::get($this->request_params,$key);
		else
			return $this->request_params;
	}

}
