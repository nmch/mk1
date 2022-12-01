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
    /** @var Database_Connection */
    protected $execute_connection;

    function set_task_history(Model_Task_History $th)
    {
        $this->lock = $th;

        return $this;
    }

    function set_execute_connection($connection)
    {
        $this->execute_connection = $connection;

        return $this;
    }

    function __construct()
    {
        $this->before();
    }

    function before() { }

    function run() { }
}
