<?php
/**
 * ロジック
 */
class Logic
{
	function __construct()
	{
		$this->before();
	}
	function __destruct()
	{
		$this->after();
	}
	function before() {}
	function after() {}
}
