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
	/** @var bool Controller::after()二重実行防止用実行済みフラグ */
	private $after_method_executed = false;
	
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
		$this->execute_after_once();
	}
	
	function execute_after_once()
	{
		if( ! $this->after_method_executed ){
			$this->after();
			$this->after_method_executed = true;
		}
	}
	
	function after(){ }
	
	function execute($name, array $arguments = [])
	{
		$r = null;
		try {
			if( method_exists($this, 'before_execute') ){
				call_user_func_array([$this, 'before_execute'], [$name, $arguments]);
			}
			
			$r = call_user_func_array([$this, $name], $arguments);
			
			if( method_exists($this, 'after_execute') ){
				call_user_func_array([$this, 'after_execute'], [$r, $name, $arguments]);
			}
		} catch(Exception $e){
			if( method_exists($this, 'onerror') ){
				$r = call_user_func_array([$this, 'onerror'], [$e, $name, $arguments]);
			}
			else{
				throw $e;
			}
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
