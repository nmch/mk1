<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Request
{
	/** @var Actionform */
	public $af;
	public $uri;
	public $method;
	/** @var \Response */
	public $response;
	/** @var \View */
	public $view;
	/** @var Exception */
	public $exception;
	
	function __construct($uri, $method = null, $data = [])
	{
		$this->uri    = $uri;
		$this->method = $method;
		$this->af     = Actionform::instance();
		if( is_array($data) ){
			$this->af->set($data);
		}
	}
	
	/**
	 * @param null $offset
	 *
	 * @return array
	 */
	function uri_segments($offset = null)
	{
		$exploded = explode('/', $this->uri);
		if( $offset === null ){
			return $exploded;
		}
		else{
			return isset($exploded[$offset]) ? $exploded[$offset] : null;
		}
	}
	
	/**
	 * @throws Exception
	 * @throws MkException
	 */
	function execute()
	{
		$route_class_name = Config::get('class.route', 'Route');
		list($controller_name, $controller_method_name, $controller_method_options) = $route_class_name::get_controller($this->uri, $this->method);
		Log::coredebug("[$route_class_name] controller = $controller_name / method = $controller_method_name");
		
		/** @var Controller $controller */
		$controller = new $controller_name([
				'request' => $this,
			]
		);
		
		// 実行するメソッドのパラメータをActioformにもセットする
		$af                           = Actionform::instance();
		$controller_method_reflection = new ReflectionMethod($controller, $controller_method_name);
		$controller_method_parameters = $controller_method_reflection->getParameters();
		foreach($controller_method_options as $option_key => $option_value){
			if( isset($controller_method_parameters[$option_key]) ){
				$af->set($controller_method_parameters[$option_key]->name, $option_value);
			}
		}
		
		$controller_return_var = call_user_func_array([$controller, 'execute'], [$controller_method_name,
		                                                                         $controller_method_options]);
		
		if( $controller_return_var === null ){
			return;
		}
		
		if( $controller_return_var instanceof Response ){
			$response = $controller_return_var;
		}
		if( is_scalar($controller_return_var) ){
			$response = new Response($controller_return_var);
		}
		if( $controller_return_var instanceof View ){
			//Log::coredebug('$controller_return_var',get_class($controller_return_var),get_object_vars($controller),get_object_vars($controller_return_var));
			// コントローラとビューの両方に定義されているプロパティをコピーする
			$controller_return_var->import_property($controller);
			
			$this->view = $controller_return_var;
			
			$response = new Response($controller_return_var, $controller->response_code());
		}
		
		if( empty($response) || ! $response instanceof Response ){
			throw new MkException('invalid response object');
		}
		
		$this->response = $response;
		
		if( Mk::is_unittesting() ){
			$response->do_not_display(true);
		}
		
		// コンテンツ送出前にController::after()を呼び出す
		// 通常はControllerのdestruct()でafter()が呼び出されるが、after()で例外が発生する場合_500_の処理前にResponse::send()が実行されていると
		// ヘッダが送出済みのため、_500_の処理が返すResponseのsend()が実行されない
		$controller->execute_after_once();
		
		$response->send();
	}
}
