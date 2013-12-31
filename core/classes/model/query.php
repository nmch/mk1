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
			
			// 後方互換性のため、order_byに限って['key' => '(asc|desc)']形式を受け付ける。
			// 本来は[ ['key','(asc|desc)'], [...] ]形式が必要。
			if($name === 'order_by'){
				if( ! Arr::is_multi($options) ){
					$options = [$options];
				}
			}
			//Log::coredebug("[model query] conditions $name", $options);
			call_user_func_array( [$this,$name], $options );
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
}
