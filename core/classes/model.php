<?
class Model implements Iterator,Countable,ArrayAccess 
{
	/*
	protected static $_table_name = NULL;
	protected static $_primary_key = NULL;
	protected static $_properties = array();
	
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
			//Log::coredebug("[model] set $name / {$this->_is_new}",$value,debug_backtrace(0,3));
			$this->_data[$name] = $value;
		}
	}
	function __get($name)
	{
		return $this->get($name);
	}
	function get($name)
	{
		if(array_key_exists($name,$this->_data))
			return $this->_data[$name];
		else if(array_key_exists($name,$this->_original))
			return $this->_original[$name];
		else if(isset(static::$_belongs_to) && array_key_exists($name,static::$_belongs_to))
			return $this->get_related('belongs_to',static::$_belongs_to[$name]);
		else if(isset(static::$_has_many) && array_key_exists($name,static::$_has_many))
			return $this->get_related('has_many',static::$_has_many[$name]);
		else
			return NULL;
	}
	function is_exist($name)
	{
		return (array_key_exists($name,$this->_data) || array_key_exists($name,$this->_original));
	}
	function _unset($name)
	{
		if(isset($this->_data[$name]))
			unset($this->_data[$name]);
		if(isset($this->_original[$name]))
			unset($this->_original[$name]);
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
						Log::coredebug("[model rel] call {$condition['method']}",$condition['args']);
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
	function as_array()
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
		
		$r = NULL;
		if($this->get($primary_key)){
			//データがある場合のみ更新する
			if(count($data))
				$r = DB::update($this->table())->values($data)->where($primary_key,$this->get($primary_key))->returning('*')->execute();
		}
		else{
			//挿入の場合、データがなくてもdefault valuesが挿入される
			$r = DB::insert($this->table())->values($data)->returning('*')->execute();
		}

		// 更新・挿入されたアイテムはoriginalとして保存し、_dataからは消す
		if($r){
			$new_data = $r->get();
			foreach($new_data as $key => $value){
				$this->_original[$key] = $value;
				if(isset($this->_data[$key]))
					unset($this->_data[$key]);
			}
		}
		
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
		$this->after_delete();
		return $r->get_affected_rows();
	}
	protected function before_delete() {}
	protected function after_delete() {}
	
	function __construct()
	{
		$this->_is_new = false;
		
		//Log::coredebug("constructed a new object of ".get_called_class()." table is ".$this->table()." / pkey is ".static::$_primary_key,reset(static::$_schema));
	}
	static function table()
	{
		return isset(static::$_table_name) ? static::$_table_name : Inflector::tableize(get_called_class());
	}
	static function primary_key()
	{
		if(empty(static::$_primary_key)){
			//スキーマからプライマリキーを取得
			$schema = Database_Schema::get(static::table());
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
	static function find()
	{
		$argc = func_num_args();
		$args = func_get_args();
		$id = isset($args[0]) ? $args[0] : NULL;
		$id_field = isset($args[1]) ? $args[1] : NULL;
			
		$query = new Model_Query(get_called_class());
		
		if(isset(static::$_join)){
			$join = static::$_join;
			if(is_array(static::$_join))
				$join = implode(" ",static::$_join);
			$query->join($join);
		}
		
		if(isset(static::$_add_field)){
			$query->add_column(static::$_add_field);
		}
		
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
			
			$data_type = Arr::get($property,'type_cat',NULL);
			$value = $this->$key;
			
			switch($data_type){
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
					break;
				case 'B':
					if(is_numeric($value)){
						$value = (boolean)$value;
					}
					else if(is_string($value)){
						//文字列表現だった場合は先頭1文字で判別
						$str = strtolower($value);
						if($str[0] == 't'){
							$value = true;
						}
						else if($str[0] == 'f'){
							$value = true;
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
					if(is_array($value))
						$value = DB::array_to_pgarraystr($value);
					break;

			}
			if($this->$key !== $value){
				//Log::coredebug("typecheck $key ($data_type) {$this->$key} → $value");
				$this->$key = $value;
			}
		}
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
