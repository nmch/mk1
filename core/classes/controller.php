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

	function __construct($options = [])
	{
		if( isset($options['request']) ){
			$this->request = $options['request'];
		}
		$this->af = Actionform::instance();
		$this->before();
	}

	function before() { }

	function __destruct()
	{
		$this->after();
	}

	function after() { }

	function execute($name, array $arguments = [])
	{
		if( method_exists($this, 'before_execute') ){
			call_user_func_array([$this, 'before_execute'], [$name, $arguments]);
		}

		$r = call_user_func_array([$this, $name], $arguments);

		if( method_exists($this, 'after_execute') ){
			call_user_func_array([$this, 'after_execute'], [$r, $name, $arguments]);
		}

		return $r;
	}

//	function before_execute($name, array $arguments = []) { }
//	function after_execute($r, $name, array $arguments = []) { }
}
