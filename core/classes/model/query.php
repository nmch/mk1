<?
class Model_Query
{
	private $model;
	private $query;
	
	function __construct($model)
	{
		$this->model = $model;
		$this->query = new Database_Query;
		$this->query->from($model::table())->set_fetch_as($model);
	}
	function get()
	{
		return $this->query->select()->execute();
	}
	function get_one()
	{
		return $this->get()->get();
	}
	
	function join()
	{
		call_user_func_array(array($this->query,'join'),func_get_args());
		return $this;
	}
	function limit()
	{
		call_user_func_array(array($this->query,'limit'),func_get_args());
		return $this;
	}
	function order_by()
	{
		call_user_func_array(array($this->query,'order_by'),func_get_args());
		return $this;
	}
	function where()
	{
		call_user_func_array(array($this->query,'where'),func_get_args());
		return $this;
	}
}
