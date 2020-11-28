<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Task
{
	/** @var Model_Task_History */
	protected $lock;
	
	function set_task_history(Model_Task_History $th)
	{
		$this->lock = $th;
		
		return $this;
	}
	
	function __construct()
	{
		$this->before();
	}
	
	function before(){ }
	
	function run(){ }
}
