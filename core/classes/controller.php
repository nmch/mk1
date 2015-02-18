<?php

/**
 * コントローラ
 */
class Controller
{
	/** @var Actionform $af */
	protected $af;
	protected $request;

	function __construct($options = [])
	{
		if( isset($options['request']) ){
			$this->request = $options['request'];
		}
		$this->af = Actionform::instance();
		$this->before();
	}

	function before()
	{
	}

	function __destruct()
	{
		$this->after();
	}

	function after()
	{
	}

	function execute($name, array $arguments = [])
	{
		return call_user_func_array([$this, $name], $arguments);
	}
}
