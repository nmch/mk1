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
	function __clone()
	{
		// $this->modelは文字列型なので実体が複製されている
		// $this->queryはオブジェクトなのでディープコピーが必要
		$this->query = clone $this->query;
	}
	function get_query()
	{
		return $this->query;
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
		$r = call_user_func_array(array($this->query,$name), $arguments);
		if($r instanceof Database_Query){
			// Database_Queryのメソッドから$this、つまりDatabase_Queryのインスタンスがかえってきた場合はそれを模倣するようModel_Queryを返す
			return $this;
		}
		else
			return $r;
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
