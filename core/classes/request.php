<?
class Request
{
	var $uri;
	
	function __construct($uri)
	{
		$this->uri = $uri;		
	}
	function uri_segments($offset = NULL)
	{
		$exploded = explode('/',$this->uri);
		if($offset === NULL)
			return $exploded;
		else
			return isset($exploded[$offset]) ? $exploded[$offset] : NULL;
	}
	
	function execute()
	{
		list($controller_name,$controller_method_name,$controller_method_options) = Route::get_controller($this->uri);
		Log::debug("[route] controller = $controller_name / method = $controller_method_name");
		
		$controller = new $controller_name(array(
			'request' => $this,
		));
		$controller_return_var = call_user_func_array(array($controller,$controller_method_name),$controller_method_options);
		
		if($controller_return_var === NULL)
			return;
		
		if($controller_return_var instanceof Response)
			$response = $controller_return_var;
		if(is_scalar($controller_return_var))
			$response = new Response($controller_return_var);
		if($controller_return_var instanceof View)
			$response = new Response($controller_return_var);
		
		if(empty($response) || ! $response instanceof Response)
			throw new MkException('invalid response object');
		
		$response->send();
	}
}
