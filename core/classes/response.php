<?
class Response
{
	private $body;
	private $status;
	private $headers;
	private $type;
	
	function __construct($body = NULL,$status = 200,array $headers = array(),$type = 'html')
	{
		Log::coredebug("[response] status=$status");
		$this->body = $body;
		$this->status = $status;
		$this->headers = $headers;
		$this->type = $type;
	}
	function send()
	{
		if( headers_sent() )
			return NULL;
		http_response_code($this->status);
		foreach($this->headers as $key => $header){
			header($key.':'.$header);
		}
		
		if($this->body instanceof View)
			$body = $this->body->render();
		else
			$body = $this->body;
		
		echo $body;
	}
	function set_header($name,$value)
	{
		$this->headers[$name] = $value;
	}
	function set_status($status)
	{
		$this->status = $status;
	}
	static function redirect($url = '', $method = 'location', $redirect_code = 302)
	{
		$response = new static;

		$response->set_status($redirect_code);

//		if (strpos($url, '://') === false)
//		{
//			$url = $url !== '' ? \Uri::create($url) : \Uri::base();
//		}
//
//		strpos($url, '*') !== false and $url = \Uri::segment_replace($url);

		if ($method == 'location')
		{
			$response->set_header('Location', $url);
		}
		elseif ($method == 'refresh')
		{
			$response->set_header('Refresh', '0;url='.$url);
		}
		else
		{
			return;
		}

		$response->send(true);
		exit;
	}
}
