<?
class Model implements Iterator,Countable,ArrayAccess 
{
	/*
	protected static $_table_name = NULL;
	protected static $_primary_key = NULL;
	protected static $_properties = array();
	
	protected static $_conditions = [
		[
			'label'		=> 'order_by',
			'name'		=> 'order_by',
			'options'	=> ['column','asc'],
		],
		[
			'label'		=> 'ignore_deleted',
			'name'		=> 'where',
			'options'	=> ['deleted',false],
		],
	];
	
	protected static $_belongs_to = array();
	
	/*
	protected static $_connection = null;
	protected static $_write_connection = null;
	protected static $_views;
	protected static $_to_array_exclude = array();
	*/
	
	protected static $_primary_keys = array();
	
	protected $_is_new = true;
	protected $_original = array();
	protected $_original_before_save = array();
	protected $_iter_keylist = array();
	protected $_iter_curkey = 0;
	public $_data = array();
	
	static function __callStatic($name, $arguments)
	{
		if(preg_match('/^find_by_(.+)$/',$name,$match) && count($arguments) >= 1){
			$column_name = $match[1];
			return static::find(reset($arguments),$column_name);
		}
	}
	function __set($name,$arg)
	{
		return $this->set($name,$arg);
	}
	function set($name,$value,$force_original = false)
	{
		if($this->_is_new || $force_original)
			$this->_original[$name] = $value;
		else if( ! array_key_exists($name,$this->_original) || $this->$name !== $value ){
			//Log::coredebug("[model] set $name / _is_new={$this->_is_new}",$value,debug_backtrace(0,3));
			$this->_data[$name] = $value;
		}
		//echo "set $name ($force_original) to "; print_r($value); print_r($this->_data);
	}
	function __get($name)
	{
		return $this->get($name);
	}
	function get_all_data()
	{
		return array_merge($this->_original,$this->_data);
	}
	/**
	 * Modelに定義されたカラムをもとにしてデータをセットする
	 * 
	 * 与えられたデータにキーが存在しないデータはNULLとしてセットされる
	 */
	function set_all_data($data)
	{
		foreach($this->columns() as $key){
			if(is_object($data))
				$this->$key = $data->$key;
			if(is_array($data))
				$this->$key = array_key_exists($key,$data) ? $data[$key] : NULL;
		}
		return $this;
	}
	function get($name, $default = NULL, $rel_options = array())
	{
		if(array_key_exists($name,$this->_data))
			return $this->_data[$name];
		else if(array_key_exists($name,$this->_original))
			return $this->_original[$name];
		else if(isset(static::$_belongs_to) && array_key_exists($name,static::$_belongs_to))
			return $this->get_related('belongs_to', Arr::merge(static::$_belongs_to[$name],$rel_options) );
		else if(isset(static::$_has_many) && array_key_exists($name,static::$_has_many))
			return $this->get_related('has_many', Arr::merge(static::$_has_many[$name],$rel_options) );
		else
			return $default;
	}
	function is_exist($name)
	{
		return (array_key_exists($name,$this->_data) || array_key_exists($name,$this->_original));
	}
	function _unset($name)
	{
		if(array_key_exists($name,$this->_data))
			unset($this->_data[$name]);
		if(array_key_exists($name,$this->_original))
			unset($this->_original[$name]);
	}
	function __unset($name)
	{
		$this->_unset($name);
	}
	
	function __clone()
	{
		$this->_data = array_merge($this->_original,$this->_data);
		$this->_original = [];
		$this->_unset($this->primary_key());
	}
	
	/**
	 * 現在のモデルのデータを引き継いだ新しいオブジェクトを作成する
	 * 
	 * データは保存されない。
	 * 
	 * @param type $ignore_list
	 * @return \static
	 */
	function duplicate($ignore_list = array())
	{
		$primary_key = static::primary_key();
		
		$new = new static;
		foreach($this->get_all_data() as $key => $value){
			if($key == $primary_key || in_array($key,$ignore_list))
				continue;
			$new->$key = $value;
		}
		return $new;
	}
	/**
	 * 現在のデータの内容を新しいレコードに保存して、新しいオブジェクトを返す
	 * 
	 * duplicate()がデータを保存しないのに対し、これはデータを保存し新しいIDができる
	 * 
	 * @param type $ignore_list
	 * @return \static
	 */
	function get_clone($ignore_list = array())
	{
		$new_obj = $this->duplicate($ignore_list);
		return $new_obj->save();
	}
	function get_related($relation_type,$options)
	{
		//Log::coredebug("rel : $relation_type",$options);
		$query = NULL;
		
		if($relation_type == 'belongs_to' || $relation_type == 'has_many'){
			$query = forward_static_call_array(array($options['model_to'],'find'),array())->where($options['key_to'],$this->$options['key_from']);
			if(isset($options['condition']) && is_array($options['condition'])){
				foreach($options['condition'] as $condition){
					if(isset($condition['method']) && isset($condition['args'])){
						//Log::coredebug("[model rel] call {$condition['method']}",$condition['args']);
						call_user_func_array(array($query,$condition['method']),$condition['args']);
					}
				}
			}
		}
		
		switch($relation_type){
			case 'belongs_to':
				return $query->get_one();
			case 'has_many':
				return $query->get();
			default:
				throw new MkException('invalid relation type');
		}
	}
	function as_array($array_key = NULL)
	{
		return array_merge($this->_original,$this->_data);
	}
	
	function get_diff()
	{
		$diff = array();
		foreach($this->_data as $key => $value){
			if(isset($this->_original[$key]) && $this->_original[$key] === $value)
				continue;
			$diff[0][$key] = array_key_exists($key,$this->_original) ? $this->_original[$key] : NULL;
			$diff[1][$key] = $value;
		}
		return $diff;
	}
	
	function save()
	{
		$primary_key = static::primary_key();
		if( ! $primary_key )
			throw new MkException('primary key required');
		
		$this->before_save();
		$this->_typecheck();
		$schema = Database_Schema::get($this->table());
		
		// DBスキーマに存在して、プライマリキーではないアイテムのみセーブ対象とする
		$data = array();
		foreach($this->_data as $key => $value){
			if($key == $primary_key)
				continue;
			if(array_key_exists($key,$schema['columns']))
				$data[$key] = $value;
		}
		//Log::coredebug("[model] save data",$this->_data,$data);
		//echo "this->_data = "; print_r($this->_data);
		//echo "data = "; print_r($data);
		
		$r = NULL;
		if($this->get($primary_key)){
			//データがある場合のみ更新する
			if(count($data)){
				$query_update = DB::update($this->table())->values($data)->where($primary_key,$this->get($primary_key))->returning('*');
				$sql_select = static::_build_select_query()->clear_from()->from('model_save_query')->get_sql();
				$sql_update = $query_update->get_sql();
				$query_update->clear_query_type()->set_sql("with model_save_query as ($sql_update) $sql_select");
				//echo "SQL = "; print_r($query_update->get_sql(true));
				$r = $query_update->execute();
			}
		}
		else{
			//挿入の場合、データがなくてもdefault valuesが挿入される
			$query_insert = DB::insert($this->table())->values($data)->returning('*');
			$sql_select = static::_build_select_query()->clear_from()->from('model_save_query')->get_sql();
			$sql_insert = $query_insert->get_sql();
			$query_insert->clear_query_type()->set_sql("with model_save_query as ($sql_insert) $sql_select");
			//echo "SQL = "; print_r($query_insert->get_sql(true));
			$r = $query_insert->execute();
		}

		// 更新・挿入されたアイテムはoriginalとして保存し、_dataからは消す
		if($r){
			$this->_original_before_save = $this->_original;
			
			$new_data = $r->get();
			//echo "new_data = "; print_r($new_data);
			//Log::coredebug("[model] new_data = ",$new_data);
			if($new_data){
				foreach($new_data as $key => $value){
					$this->_original[$key] = $value;
					if(array_key_exists($key,$this->_data))
						unset($this->_data[$key]);
				}
			}
			else{
				// セーブに失敗した(保存できたデータがない)場合はdataを消し、originalの状態に戻して例外をスロー
				$this->_data = [];
				throw new Exception('save failed');
			}
		}
		//Log::coredebug("[model] saved data = ",$this);
		
		$this->after_save();
		return $this;
	}
	protected function before_save() {}
	protected function after_save() {}

	function delete()
	{
		$primary_key = static::primary_key();
		if( ! $primary_key )
			throw new MkException('primary key required');
		if( ! $this->get($primary_key) )
			throw new MkException('empty primary key');
		
		$this->before_delete();
		$r = DB::delete()->from($this->table())->where($primary_key,$this->get($primary_key))->execute();
		unset($this->$primary_key);
		$this->after_delete();
		return $r->get_affected_rows();
	}
	protected function before_delete() {}
	protected function after_delete() {}
	
	function __construct($options = array())
	{
		if(empty($options['deferred_init']))
			$this->_is_new = false;
		
		//Log::coredebug("constructed a new object of ".get_called_class()." table name is ".$this->table()." / pkey is ".static::primary_key(),$options);
	}
	function drop_isnew_flag()
	{
		$this->_is_new = false;
		return $this;
	}
	static function table()
	{
		return isset(static::$_table_name) ? static::$_table_name : Inflector::tableize(get_called_class());
	}
	/**
	 * データ取得時の強制条件を取得する
	 *
	 * Model_Query::get() から呼ばれる
	 */
	static function conditions()
	{
		return isset(static::$_conditions) ? static::$_conditions : array();
	}
	static function primary_key()
	{
		if(empty(static::$_primary_key)){
			//スキーマからプライマリキーを取得
			$schema = Database_Schema::get(static::table(),[]);
			if(Arr::get($schema,'has_pkey'))
				$primary_key = Arr::get($schema,'primary_key',array());
			else
				throw new MkException('empty primary key');
		}
		else
			$primary_key = static::$_primary_key;
		
		if(is_array($primary_key)){
			if(count($primary_key) == 0)
				throw new MkException('empty primary key');
			if(count($primary_key) != 1)
				throw new MkException('too many primary keys');
			
			$primary_key = reset($primary_key);
		}
		
		static::$_primary_keys[get_called_class()] = $primary_key;
		
		return $primary_key;
	}
	
	protected static function _build_select_query()
	{
		$query = new Model_Query(get_called_class());
		$query->select('*');
		
		if(isset(static::$_join)){
			$join = static::$_join;
			if(is_array(static::$_join))
				$join = implode(" ",static::$_join);
			$query->join($join);
		}
		
		if( ! empty(static::$_add_field) ){
			$query->select(static::$_add_field);
		}
		
		return $query;
	}
	/**
	 * 特定の行を取得する
	 *
	 * find(ID, ID_FIELD, IGNORE_CONDITION_LABELS)
	 * ID_FIELDがfalse判定の場合はプライマリキーを使用する
	 */
	static function find()
	{
		$argc = func_num_args();
		$args = func_get_args();
		$id = Arr::get($args,'0');
		$id_field = Arr::get($args,'1');
		$ignore_conditions = Arr::get($args,'2');
		
		$query = static::_build_select_query();
		$query->ignore_conditions($ignore_conditions);
		
		if( $argc ){
			if($id === 'all'){	// 型判定なし(==)で比較すると文字列を比較する際に文字列が数値にキャストされてしまう。そのため$idに0が入っているとtrueになるので注意。
				return $query->get();
			}
			else{
				if( ! $id_field )
					$id_field = static::primary_key();
				$r = $query->where($id_field,$id)->get_one();
				if( $r === NULL )
					throw new RecordNotFoundException;
				return $r;
			}
		}
		else{
			return $query;
		}
	}
	function reload()
	{
		$query = $this->_build_select_query();
		$id_field = static::primary_key();
		$r = $query->where($id_field,$this->$id_field)->get_one();
		if( $r === NULL )
			throw new RecordNotFoundException;
		
		$this->_original = [];
		foreach($r as $key => $value){
			$this->_original[$key] = $value;
		}
		
		return $this;
	}
	static function get_all()
	{
		return static::find('all');
	}
	
	protected function _typecheck($throw = NULL, $skip_unchanged_item = true)
	{
		$schema = Database_Schema::get($this->table());
		//Log::coredebug("typecheck",static::$_schema['columns']);
		if(empty($schema['columns'])){
			return;
		}
		
		foreach($schema['columns'] as $key => $property){
			//Log::coredebug("[model typecheck] $key",$property);
			//echo "[model typecheck] $key"; print_r($property);
			/*
			if( is_numeric($key) && ! is_array($property) ){
				$key = $property;
				if($key == static::$_primary_key){
					$property = array(
						'data_type' => 'integer',
					);
				}
				else
					$property = array();
			}
			*/
			if($skip_unchanged_item && !array_key_exists($key, $this->_data))
				continue;
			
			$data_type = Arr::get($property,'type',NULL);		// int4, timestamp, text
			$type_cat = Arr::get($property,'type_cat',NULL);	// S, N, A, U
			$value = $this->$key;
			
			switch($type_cat){
				case 'N':
					if( ! is_numeric($value) )
						$value = NULL;
					break;
//					if( is_numeric($value) && -32768 <= $value && $value <= 32767){
//					if(is_numeric($value) && -2147483648 <= $value && $value <= 2147483647){
//					if(is_numeric($value) && -9223372036854775808 <= $value && $value <= 9223372036854775807){
				case 'D':
					// UNIXタイムスタンプだった場合は変換する
					if(is_numeric($value))
						$value = date(DATE_ATOM,$value);
					// 日付として空文字列は受け付けられないので、NULLにする
					if($value === "")
						$value = NULL;
					break;
				case 'B':
					if(is_numeric($value)){
						$value = (boolean)$value;
					}
					else if(is_string($value)){
						//文字列表現だった場合は先頭1文字(t/f)もしくはon/offで判別
						$str = strtolower($value);
						if((strlen($str) && $str[0] == 't') || strtolower($str) === 'on'){
							$value = true;
						}
						else if((strlen($str) && $str[0] == 'f') || strtolower($str) === 'off'){
							$value = false;
						}
						else
							$value = NULL;
					}
					if($value === true)
						$value = 't';
					else if($value === false)
						$value = 'f';
					else
						$value = NULL;
					break;
				case 'A':
					//Log::debug($property);
					$type_detail = Database_Type::get($data_type);
					//Log::debug($type_detail);
					$elem_type = Database_Type::get_by_oid($type_detail['typelem']);
					//Log::debug($elem_type);
					if(is_array($value))
						$value = DB::array_to_pgarraystr($value,$type_detail['typdelim'],$elem_type['typcategory']);
					break;
				case 'U':
					if($data_type === 'json'){
						$value = json_encode($value);
					}
					break;
			}
			if($this->$key !== $value){
				//Log::coredebug("typecheck $key ($data_type) {$this->$key} → $value");
				$this->$key = $value;
			}
		}
	}
	
	protected static function make_unique_code($column_name, $length = 32)
	{
		$char_seed = array_merge(range('a','z'),range('A','Z'),range('0','9'));
		
		$unique_code = "";
		for($c = 0;$c < 100;$c++){
			$unique_code_candidate = "";
			for($i = 0;$i < $length;$i++){
				$unique_code_candidate .= $char_seed[ mt_rand(0,count($char_seed) - 1) ];
			}
			
			$r = DB::select($column_name)->from(static::table())->where($column_name,$unique_code_candidate)->execute();
			if( $r->count() == 0){
				$unique_code = $unique_code_candidate;
				break;
			}
		}
		if( ! $unique_code )
			throw new Exception('generate unique id failed');
		
		return $unique_code;
	}
	
	/*
	public static function instance_from_query_data()
	{
		$db_query = forward_static_call_array('DB::query',func_get_args());
		return $db_query->set_fetch_as(get_called_class())->execute();
	}
	 * 
	 */
			
	public function offsetSet($offset, $value)
	{
		return $this->set($offset,$value);
	}
	public function offsetExists($offset)
	{
		return $this->is_exist($offset);
	}

	public function offsetUnset($offset)
	{
		$this->_unset($offset);
	}
	public function offsetGet($offset)
	{
		return $this->get($offset);
	}
	function columns()
	{
		$schema = $this->schema();
		return array_keys($schema['columns']);
	}
	function schema()
	{
		return Database_Schema::get($this->table());
	}
	function keys()
	{
		return array_merge(array_keys($this->_original),array_keys($this->_data));
	}
	function rewind() {
		$this->_iter_keylist = $this->keys();
		$this->_iter_curkey = 0;
	}
	function current() {
		return $this->get( $this->key() );
	}
	function key() {
		return $this->_iter_keylist[$this->_iter_curkey];
	}
	function next() {
		if($this->valid())
			$this->_iter_curkey++;
	}
	function valid() {
		return ($this->_iter_curkey < $this->count());
	}
	function count()
	{
		return count($this->_iter_keylist);
	}
}
