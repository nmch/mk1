<?
class Model_Query
{
	private $model;
	private $query;
	private $ignore_conditions = [];
	private $conditions_applied = false;
	
	function __construct($model)
	{
		$this->model = $model;
		$this->query = new Database_Query;
		return $this->query->from($model::table())->set_fetch_as($model);
	}
	function get_model()
	{
		return $this->model;
	}
	function __clone()
	{
		// $this->modelは文字列型なので実体が複製されている
		// $this->queryはオブジェクトなのでディープコピーが必要
		$this->query = clone $this->query;
	}
	/**
	 * static::$_conditionsをqueryに反映する
	 */
	function apply_conditions($force = false)
	{
		if($this->conditions_applied && ! $force){
			return $this;
		}
		
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
					// 'options' => ['test_int1','asc'] 形式を変換
					if(count($options) === 2 && (Arr::get($options,'1') === 'asc' || Arr::get($options,'1') === 'desc')){
						$options = [
							[Arr::get($options,'0'), Arr::get($options,'1')]
						];
					}
					
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
				Log::debug($options);
				foreach($options as $option){
					call_user_func_array( [$this,$name], $option );
				}
			}
			else{
				//Log::coredebug("[model query] conditions $name", $options);
				call_user_func_array( [$this,$name], $options );
			}
		}
		
		$this->conditions_applied = true;
		
		return $this;
	}
	function get_query()
	{
		$this->apply_conditions();
		
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
