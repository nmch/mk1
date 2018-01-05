<?php

/**
 * コントローラ
 */
class Controller
{
	/** @var Actionform $af */
	protected $af;
	/** @var  Request */
	protected $request;
	protected $response_code = 200;
	
	function __construct($options = [])
	{
		if( isset($options['request']) ){
			$this->request = $options['request'];
		}
		$this->af = Actionform::instance();
		$this->before();
	}
	
	function before(){ }
	
	function __destruct()
	{
		$this->after();
	}
	
	function after(){ }
	
	function execute($name, array $arguments = [])
	{
		if( method_exists($this, 'before_execute') ){
			call_user_func_array([$this, 'before_execute'], [$name, $arguments]);
		}
		
		$r = null;
		try {
			$r = call_user_func_array([$this, $name], $arguments);
		} catch(Exception $e){
			if( method_exists($this, 'onerror') ){
				$r = call_user_func_array([$this, 'onerror'], [$e, $name, $arguments]);
			}
			else{
				throw $e;
			}
		}
		
		if( method_exists($this, 'after_execute') ){
			call_user_func_array([$this, 'after_execute'], [$r, $name, $arguments]);
		}
		
		return $r;
	}
	
	function response_code($code = null)
	{
		if( $code ){
			$this->response_code = $code;
		}
		
		return $this->response_code;
	}
	
	//	function before_execute($name, array $arguments = []) { }
	//	function after_execute($r, $name, array $arguments = []) { }
}
