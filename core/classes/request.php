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
	const METHOD_GET     = 'GET';
	const METHOD_HEAD    = 'HEAD';
	const METHOD_POST    = 'POST';
	const METHOD_PUT     = 'PUT';
	const METHOD_DELETE  = 'DELETE';
	const METHOD_CONNECT = 'CONNECT';
	const METHOD_OPTIONS = 'OPTIONS';
	const METHOD_TRACE   = 'TRACE';
	const METHOD_PATCH   = 'PATCH';
	
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
	/** @var Request */
	protected $prev_request;
	
	function __construct($uri, $method = null, $data = null, $af = null)
	{
		$this->uri($uri);
		$this->method($method);
		$this->af = ($af ?? Actionform::instance());
		if( is_array($data) ){
			$this->af->set($data);
		}
	}
	
	/**
	 * リクエストURIの取得・変更
	 *
	 * @param string $uri
	 *
	 * @return string
	 */
	function uri($uri = null)
	{
		if( $uri !== null ){
			$this->uri = $uri;
		}
		
		return $this->uri;
	}
	
	/**
	 * 直前のリクエストの取得・変更
	 *
	 * @param Request|null $prev_request
	 *
	 * @return Request|null
	 */
	function prev_request($prev_request = null)
	{
		if( $prev_request !== null ){
			$this->prev_request = $prev_request;
		}
		
		return $this->prev_request;
	}
	
	/**
	 * 例外の取得・変更
	 *
	 * @param Exception|null $exception
	 *
	 * @return Exception|null
	 */
	function exception($exception = null)
	{
		if( $exception !== null ){
			$this->exception = $exception;
		}
		
		return $this->exception;
	}
	
	/**
	 * リクエストメソッドの取得・変更
	 *
	 * @param string $method
	 *
	 * @return string
	 */
	function method($method = null)
	{
		if( $method !== null ){
			$this->method = strtoupper($method);
		}
		
		return strtoupper($this->method);
	}
	
	/**
	 * @param null $offset
	 *
	 * @return array|string
	 */
	function uri_segments($offset = null)
	{
		$exploded = explode('/', $this->uri);
		
		return ($offset === null) ? $exploded : ($exploded[$offset] ?? null);
	}
	
	/**
	 * @throws Exception
	 * @throws MkException
	 */
	function execute()
	{
		$route_class_name = Config::get('class.route', 'Route');
		[$controller_name, $controller_method_name, $controller_method_options] = $route_class_name::get_controller($this->uri, $this->method);
		Log::coredebug("[$route_class_name] controller = $controller_name / method = $controller_method_name");
		
		/** @var Controller $controller */
		$controller = new $controller_name([
				'request' => $this,
				'af'      => $this->af,
			]
		);
		
		// 実行するメソッドのパラメータをActioformにもセットする
		$controller_method_reflection = new ReflectionMethod($controller, $controller_method_name);
		$controller_method_parameters = $controller_method_reflection->getParameters();
		foreach($controller_method_options as $option_key => $option_value){
			if( isset($controller_method_parameters[$option_key]) ){
				$this->af->set($controller_method_parameters[$option_key]->name, $option_value);
			}
		}
		
		$controller_return_var = call_user_func_array(
			[$controller, 'execute'],
			[$controller_method_name, $controller_method_options]
		);
		
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
