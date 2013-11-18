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
		$route_class_name = Config::get('class.route','Route');
		list($controller_name,$controller_method_name,$controller_method_options) = $route_class_name::get_controller($this->uri);
		Log::coredebug("[$route_class_name] controller = $controller_name / method = $controller_method_name");
		
		$controller = new $controller_name(array(
			'request' => $this,
		));
		
		// 実行するメソッドのパラメータをActioformにもセットする
		$af = Actionform::instance();
		$controller_method_reflection = new ReflectionMethod($controller,$controller_method_name);
		$controller_method_parameters = $controller_method_reflection->getParameters();
		foreach($controller_method_options as $option_key => $option_value){
			if(isset($controller_method_parameters[$option_key])){
				$af->set($controller_method_parameters[$option_key]->name, $option_value);
			}
		}
		
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
