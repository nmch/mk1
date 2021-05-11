<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Task_G
{
	function run($type, $name)
	{
		$this->{"generate_" . $type}($name);
	}

	function t()
	{
		$tables = Database_Schema::retrieve();
//		print_r($tables);
	}
}