<?php
class Task_G
{
	function run($type,$name)
	{
		$this->{"generate_".$type}($name);
	}
	function t()
	{
		$tables = Database_Schema::retrieve();
//		print_r($tables);
	}
}