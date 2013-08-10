<?
class Model_Query
{
	private $model;
	private $query;
	
	function __construct($model)
	{
		$this->model = $model;
		$this->query = new Database_Query;
		return $this->query->from($model::table())->set_fetch_as($model);
	}
	function get()
	{
		$conditions = forward_static_call(array($this->model,'conditions'));
		
		$this->query->order_by(Arr::get($conditions,'order_by',array()));
		
		return $this->query->select('*')->execute();
	}
	/**
	 * get()のエイリアス
	 * 
	 * Database_Pager::execute()からDatabase_Queryと同じexecute()として呼び出される
	 */
	function execute()
	{
		return call_user_func_array(array($this,'get'), func_get_args());
	}
	function get_one()
	{
		return $this->get()->get();
	}
	
	function __call($name , array $arguments)
	{
		call_user_func_array(array($this->query,$name), $arguments);
		return $this;
	}
	/*
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
	function add_column()
	{
		call_user_func_array(array($this->query,'select'),func_get_args());
		return $this;
	}
	*/
}
