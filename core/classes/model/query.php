<?
class Model_Query
{
	private $model;
	private $query;
	private $ignore_conditions = [];
	
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
		$conditions = forward_static_call(array($this->model,'conditions'));
		
		foreach($conditions as $index => $condition){
			if( ! is_numeric($index) ){
				$label	= NULL;
				$name	= $index;
				$options= $condition;
			}
			else{
				$label	= Arr::get($condition, 'label');
				$name	= Arr::get($condition, 'name');
				$options= Arr::get($condition, 'options');
			}
			
			// 無視するコンディションリストにラベルが登録されていた場合はスキップ
			if($label && in_array($label,$this->ignore_conditions)){
				continue;
			}
			
			// order_byは複数の形式をとることが可能。tests/model.phpのtestConditionsOrderby()参照。
			if($name === 'order_by'){
				if(is_string($options)){
					$options = [
						[$options, 'asc']
					];
				}
				elseif(is_array($options)){
					$new_options = [];
					foreach($options as $key => $value){
						if( ! is_numeric($key) && is_scalar($value) ){
							// 'column' => 'asc' 形式
							$new_options[] = [$key, $value];
						}
						elseif( is_array($value) && count($value) == 2 ){
							// ['column' , 'asc'] 形式
							$new_options[] = $value;
						}
					}
					$options = $new_options;
				}
				foreach($options as $option){
					call_user_func_array( [$this,$name], $option );
				}
			}
			else{
				//Log::coredebug("[model query] conditions $name", $options);
				call_user_func_array( [$this,$name], $options );
			}
		}
		//$this->query->order_by(Arr::get($conditions,'order_by',array()));
		
		return $this->query;
	}
	function clear_ignore_conditions()
	{
		$this->ignore_conditions = [];
	}
	function ignore_conditions($ignore_conditions)
	{
		if( ! is_array($ignore_conditions) ){
			$ignore_conditions = [$ignore_conditions];
		}
		$this->ignore_conditions = array_merge($this->ignore_conditions, $ignore_conditions);
	}
	function get()
	{
		return $this->get_query()->select('*')->execute();
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
