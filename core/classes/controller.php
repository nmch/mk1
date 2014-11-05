<?php
/**
 * コントローラ
 */
class Controller
{
	protected $af;
	protected $request;
	
	function __construct($options = array())
	{
		if(isset($options['request']))
			$this->request = $options['request'];
		$this->af = Actionform::instance();
		$this->before();
	}
	function __destruct()
	{
		$this->after();
	}
	function before() {}
	function after() {}
	
	function execute($name , array $arguments = [])
	{
		return call_user_func_array(array($this,$name), $arguments);
	}
}
