<?php

/**
 * Model
 */
class Model implements Iterator, Countable, ArrayAccess
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

	protected static $_primary_keys         = [];
	public           $_data                 = [];
	protected        $_is_new               = true;
	protected        $_original             = [];
	protected        $_original_before_save = [];
	protected        $_iter_keylist         = [];
	protected        $_iter_curkey          = 0;
	/** @var array save()時にとられるdiff */
	protected $_save_diff = [];

	function __construct($options = [])
	{
		if( empty($options['deferred_init']) ){
			$this->_is_new = false;
		}

		$this->after_load();

		//Log::coredebug("constructed a new object of ".get_called_class()." table name is ".$this->table()." / pkey is ".static::primary_key(),$options);
	}

	protected function after_load()
	{
	}

	static function __callStatic($name, $arguments)
	{
		if( preg_match('/^find_by_(.+)$/', $name, $match) && count($arguments) >= 1 ){
			$column_name = $match[1];

			return static::find(reset($arguments), $column_name);
		}
	}

	/**
	 * 特定の行を取得する
	 *
	 * find(ID, ID_FIELD, IGNORE_CONDITION_LABELS)
	 * ID_FIELDがfalse判定の場合はプライマリキーを使用する
	 *
	 * @return Database_Resultset|Model_Query|Model
	 * @throws MkException
	 * @throws RecordNotFoundException
	 */
	static function find()
	{
		$argc              = func_num_args();
		$args              = func_get_args();
		$id                = Arr::get($args, '0');
		$id_field          = Arr::get($args, '1');
		$ignore_conditions = Arr::get($args, '2');
		$ignore_joins      = Arr::get($args, '3');

		$query = static::_build_select_query();
		$query->ignore_joins($ignore_joins);
		$query->ignore_conditions($ignore_conditions);

		if( $argc ){
			if( $id === 'all' ){    // 型判定なし(==)で比較すると文字列を比較する際に文字列が数値にキャストされてしまう。そのため$idに0が入っているとtrueになるので注意。
				return $query->get();
			}
			else{
				//Log::info("[Model] find single record $id",debug_backtrace());
				if( ! $id_field ){
					$id_field = static::primary_key();
				}
				$r = $query->where($id_field, $id)->get_one();
				if( $r === null ){
					throw new RecordNotFoundException;
				}

				return $r;
			}
		}
		else{
			return $query;
		}
	}

	/**
	 * @return Model_Query
	 */
	protected static function _build_select_query()
	{
		$query = new Model_Query(get_called_class());
		$query->select('*');

		if( ! empty(static::$_add_field) ){
			$query->select(static::$_add_field);
		}

		return $query;
	}

	static function primary_key()
	{
		if( empty(static::$_primary_key) ){
			//スキーマからプライマリキーを取得
			$schema = Database_Schema::get(static::table(), []);
			if( Arr::get($schema, 'has_pkey') ){
				$primary_key = Arr::get($schema, 'primary_key', []);
			}
			else{
				throw new MkException('empty primary key on ' . static::table());
			}
		}
		else{
			$primary_key = static::$_primary_key;
		}

		if( is_array($primary_key) ){
			if( count($primary_key) == 0 ){
				throw new MkException('empty primary key');
			}
			if( count($primary_key) != 1 ){
				throw new MkException('too many primary keys');
			}

			$primary_key = reset($primary_key);
		}

		static::$_primary_keys[get_called_class()] = $primary_key;

		return $primary_key;
	}

	static function table()
	{
		return isset(static::$_table_name) ? static::$_table_name : Inflector::tableize(get_called_class());
	}

	static function _get_join_items()
	{
		$join = isset(static::$_join) ? static::$_join : [];
		if( ! is_array($join) ){
			$join = [$join];
		}

		if( empty(static::$_do_not_inherit_join) ){
			$parent = get_parent_class(get_called_class());
			if( $parent ){
				$join = array_merge($join, $parent::_get_join_items());
			}
			$join = array_unique($join);
			ksort($join);
		}

		//		Log::coredebug("_get_join_items",get_called_class(),$join);

		return $join;
	}

	/**
	 * データ取得時の強制条件を取得する
	 *
	 * @see Model_Query::get()
	 */
	static function conditions()
	{
		return isset(static::$_conditions) ? static::$_conditions : [];
	}
	/**
	 * データ取得時のJOIN条件を取得する
	 *
	 * @see Model_Query::get()
	 */
	/*
	static function joins()
	{
		Log::coredebug("joins = ",get_called_class());
		return isset(static::$_join) ? static::$_join : [];
	}
	*/

	static function get_all()
	{
		return static::find('all');
	}

	/**
	 * 指定カラムでユニークとなるコードを生成する
	 *
	 * @returns string
	 * @throws Exception
	 */
	public static function make_unique_code($column_name, $length = 32, $char_seed = [])
	{
		$unique_code = "";
		for($c = 0; $c < 100; $c++){
			$unique_code_candidate = Mk::make_random_code($length, $char_seed);

			$r = DB::select($column_name)->from(static::table())->where($column_name, $unique_code_candidate)->execute();
			if( $r->count() == 0 ){
				$unique_code = $unique_code_candidate;
				break;
			}
		}
		if( ! $unique_code ){
			throw new Exception('generate unique id failed');
		}

		return $unique_code;
	}

	/**
	 * joinsetを取得する
	 *
	 * @param string $name
	 *
	 * @return array
	 */
	static function joinset($name)
	{
		return isset(static::$_joinset) ? Arr::get(static::$_joinset, $name, []) : [];
	}

	function set_array(array $data, $force_original = false)
	{
		foreach($data as $key => $value){
			$this->set($key, $value, $force_original);
		}

		return $this;
	}

	function set($name, $value, $force_original = false)
	{
		if( $this->_is_new || $force_original ){
			$this->_original[$name] = $value;
		}
		else{
			if( ! array_key_exists($name, $this->_original) || $this->$name !== $value ){
				//Log::coredebug("[model] set $name / _is_new={$this->_is_new}",$value,debug_backtrace(0,3));
				$this->_data[$name] = $value;
			}
		}

		//echo "set $name ($force_original) to "; print_r($value); print_r($this->_data);
		return $this;
	}

	function __get($name)
	{
		return $this->get($name);
	}

	function __set($name, $arg)
	{
		return $this->set($name, $arg);
	}

	function get($name, $default = null, $rel_options = [])
	{
		if( array_key_exists($name, $this->_data) ){
			return $this->_data[$name];
		}
		else{
			if( array_key_exists($name, $this->_original) ){
				return $this->_original[$name];
			}
			else{
				if( isset(static::$_belongs_to) && array_key_exists($name, static::$_belongs_to) ){
					return $this->get_related('belongs_to', Arr::merge(static::$_belongs_to[$name], $rel_options));
				}
				else{
					if( isset(static::$_has_many) && array_key_exists($name, static::$_has_many) ){
						return $this->get_related('has_many', Arr::merge(static::$_has_many[$name], $rel_options));
					}
					else{
						return $default;
					}
				}
			}
		}
	}

	function get_related($relation_type, $options)
	{
		//Log::coredebug("Model::get_related() : $relation_type", $options);
		$query = null;

		if( $relation_type == 'belongs_to' || $relation_type == 'has_many' ){

			$method     = [$options['model_to'], 'find'];
			$key_from   = $options['key_from'];
			$from_value = $this->{$key_from};
			$query      = forward_static_call_array($method, [])->where($options['key_to'], $from_value);
			if( isset($options['condition']) && is_array($options['condition']) ){
				foreach($options['condition'] as $condition){
					if( isset($condition['method']) && isset($condition['args']) ){
						//Log::coredebug("[model rel] call {$condition['method']}",$condition['args']);
						call_user_func_array([$query, $condition['method']], $condition['args']);
					}
				}
			}
		}

		switch($relation_type){
			case 'belongs_to':
				$target = $query->get_one();
				if( $target === null && Arr::get($options, 'autogen') ){
					// autogenモードの場合は自動的にオブジェクトを作って返す (saveはしない)
					$target                       = new $options['model_to'];
					$target->{$options['key_to']} = $this->{$options['key_from']};
				}

				return $target;
			case 'has_many':
				return $query->get();
			default:
				throw new MkException('invalid relation type');
		}
	}

	/**
	 * Modelに定義されたカラムをもとにしてデータをセットする
	 *
	 * @param array|object $data       データ
	 * @param boolean      $empty2null 与えられたデータにキーが存在しないデータはNULLとしてセットされる
	 *
	 * @return $this
	 */
	function set_all_data($data, $empty2null = true)
	{
		foreach($this->columns() as $key){
			if( is_object($data) ){
				$this->$key = $data->$key;
			}
			if( is_array($data) ){
				if( array_key_exists($key, $data) || $empty2null ){
					$this->$key = Arr::get($data, $key);
				}
			}
		}

		return $this;
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

	function __unset($name)
	{
		$this->_unset($name);
	}

	function _unset($name)
	{
		if( array_key_exists($name, $this->_data) ){
			unset($this->_data[$name]);
		}
		if( array_key_exists($name, $this->_original) ){
			unset($this->_original[$name]);
		}
	}

	function __clone()
	{
		$this->_data     = array_merge($this->_original, $this->_data);
		$this->_original = [];
		$this->_unset($this->primary_key());
	}

	/**
	 * 現在のデータの内容を新しいレコードに保存して、新しいオブジェクトを返す
	 *
	 * duplicate()がデータを保存しないのに対し、これはデータを保存し新しいIDができる
	 *
	 * @param array $ignore_list
	 *
	 * @return \static
	 */
	function get_clone($ignore_list = [])
	{
		$new_obj = $this->duplicate($ignore_list);

		return $new_obj->save();
	}

	/**
	 * 現在のモデルのデータを引き継いだ新しいオブジェクトを作成する
	 *
	 * データは保存されない。
	 *
	 * @param array $ignore_list
	 *
	 * @return Model
	 */
	function duplicate($ignore_list = [])
	{
		$primary_key = static::primary_key();

		$new = new static;
		foreach($this->get_all_data() as $key => $value){
			if( $key == $primary_key || in_array($key, $ignore_list) ){
				continue;
			}
			$new->$key = $value;
		}

		return $new;
	}

	function get_all_data()
	{
		return array_merge($this->_original, $this->_data);
	}

	function save()
	{
		$primary_key = static::primary_key();
		if( ! $primary_key ){
			throw new MkException('primary key required');
		}

		$this->before_save();
		$this->_typecheck();
		$schema = Database_Schema::get($this->table());

		// DBスキーマに存在して、プライマリキーではないアイテムのみセーブ対象とする
		$data = [];
		foreach($this->_data as $key => $value){
			if( $key == $primary_key ){
				continue;
			}
			if( array_key_exists($key, $schema['columns']) ){
				$data[$key] = $value;
			}
		}
		//Log::coredebug("[model] save data",$this->_data,$data);
		//echo "this->_data = "; print_r($this->_data);
		//echo "data = "; print_r($data);

		try {
			$r = null;
			if( $this->get($primary_key) ){
				//データがある場合のみ更新する
				if( count($data) ){
					$query_update = DB::update($this->table())->values($data)->where($primary_key, $this->get($primary_key))->returning('*');
					// apply_conditions()を実行するとexcept系のconditionにひっかかって保存したデータがselectできないことがある
					//				$sql_select   = static::_build_select_query()->apply_joins()->apply_conditions()->clear_from()->from('model_save_query')->get_sql();
					$sql_select = static::_build_select_query()->apply_joins()->clear_from()->from('model_save_query')->get_sql();
					$sql_update = $query_update->get_sql();
					$query_update->clear_query_type()->set_sql("with model_save_query as ($sql_update) $sql_select");
					//echo "SQL = "; print_r($query_update->get_sql(true));
					$r = $query_update->execute();
				}
			}
			else{
				//挿入の場合、データがなくてもdefault valuesが挿入される
				$query_insert = DB::insert($this->table())->values($data)->returning('*');
				// apply_conditions()を実行するとexcept系のconditionにひっかかって保存したデータがselectできないことがある
				//			$sql_select   = static::_build_select_query()->apply_joins()->apply_conditions()->clear_from()->from('model_save_query')->get_sql();
				$sql_select = static::_build_select_query()->apply_joins()->clear_from()->from('model_save_query')->get_sql();
				$sql_insert = $query_insert->get_sql();
				$query_insert->clear_query_type()->set_sql("with model_save_query as ($sql_insert) $sql_select");
				//echo "SQL = "; print_r($query_insert->get_sql(true));
				$r = $query_insert->execute();
			}
		} catch(Exception $e){
			$on_save_error_handler_name = 'on_save_error';
			if( method_exists($this, $on_save_error_handler_name) ){
				$on_save_error_handler_result = call_user_func([$this, $on_save_error_handler_name], $e);
				if( $on_save_error_handler_result === null ){
					// ハンドラが明示的にnull以外を返さなかった場合は例外を投げる
					throw $e;
				}
			}
			else{
				throw $e;
			}
		}

		// 更新・挿入されたアイテムはoriginalとして保存し、_dataからは消す
		if( $r ){
			$this->_original_before_save = $this->_original;

			$new_data         = $r->get();
			$this->_save_diff = [];

			//echo "new_data = "; print_r($new_data);
			//Log::debug2("[model] new_data = ",$new_data,$this->_original);
			if( $new_data ){
				foreach($new_data as $key => $value){
					//Log::debug2("save key",$key,$this->get($key),$value);
					if( $this->{$key} !== $value ){
						$this->_save_diff[0][$key] = $this->$key;
						$this->_save_diff[1][$key] = $value;
						//Log::debug2("save diff",$this->_save_diff);
					}
					$this->_original[$key] = $value;
					if( array_key_exists($key, $this->_data) ){
						unset($this->_data[$key]);
					}
				}
				// DBから返ってきた新しいデータをセットしたあとは初期化された直後と同じとみなしてafter_load()を呼ぶ
				$this->after_load();
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

	public function get_save_diff($key = null)
	{
		if( $key ){
			return [
				Arr::get($this->_save_diff, '0.' . $key),
				Arr::get($this->_save_diff, '1.' . $key),
			];
		}
		else{
			return $this->_save_diff;
		}
	}

	protected function before_save(){ }

	protected function _typecheck($throw = null, $skip_unchanged_item = true)
	{
		$schema = Database_Schema::get($this->table());
		//Log::coredebug("typecheck",static::$_schema['columns']);
		if( empty($schema['columns']) ){
			return;
		}

		foreach($schema['columns'] as $key => $property){
			//			Log::coredebug("[model typecheck]", $key, $property);
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
			if( $skip_unchanged_item && ! array_key_exists($key, $this->_data) ){
				continue;
			}

			$data_type = Arr::get($property, 'type', null);        // int4, timestamp, text
			$type_cat  = Arr::get($property, 'type_cat', null);    // S, N, A, U
			$value     = $this->$key;

			switch($type_cat){
				case 'N':
					if( ! is_numeric($value) ){
						$value = null;
					}
					break;
				//					if( is_numeric($value) && -32768 <= $value && $value <= 32767){
				//					if(is_numeric($value) && -2147483648 <= $value && $value <= 2147483647){
				//					if(is_numeric($value) && -9223372036854775808 <= $value && $value <= 9223372036854775807){
				case 'D':
					// UNIXタイムスタンプだった場合は変換する
					if( is_numeric($value) ){
						$value = date(DATE_ATOM, $value);
					}
					// 日付として空文字列は受け付けられないので、NULLにする
					if( $value === "" ){
						$value = null;
					}
					break;
				case 'B':
					if( is_numeric($value) ){
						$value = (boolean)$value;
					}
					else{
						if( is_string($value) ){
							//文字列表現だった場合は先頭1文字(t/f)もしくはon/offで判別
							$str = strtolower($value);
							if( (strlen($str) && $str[0] == 't') || strtolower($str) === 'on' ){
								$value = true;
							}
							else{
								if( (strlen($str) && $str[0] == 'f') || strtolower($str) === 'off' ){
									$value = false;
								}
								else{
									$value = null;
								}
							}
						}
					}
					if( $value === true ){
						$value = 't';
					}
					else{
						if( $value === false ){
							$value = 'f';
						}
						else{
							$value = null;
						}
					}
					break;
				case 'A':
					//Log::coredebug($property);
					$type_detail = Database_Type::get($data_type);
					//Log::coredebug($type_detail);
					$elem_type = Database_Type::get_by_oid($type_detail['typelem']);
					//Log::coredebug($elem_type);
					if( is_array($value) ){
						$value = DB::array_to_pgarraystr($value, $type_detail['typdelim'], $elem_type['typcategory']);
					}
					break;
				case 'U':
					if( $data_type === 'json' || $data_type === 'jsonb' ){
						$value = $value === null ? null : json_encode($value);
					}
					if( $data_type === 'hstore' ){
						// fixme
					}
					break;
			}
			if( $this->$key !== $value ){
				//Log::coredebug("typecheck $key ($data_type) {$this->$key} → $value");
				$this->$key = $value;
			}
		}
	}

	protected function after_save(){ }

	/**
	 * 同じテーブル内で別のIDからデータをコピーする
	 *
	 * @param mixed $src_id
	 * @param array $excludes
	 * @param array $includes
	 *
	 * @return $this
	 * @throws DatabaseQueryError
	 * @throws MkException
	 * @throws RecordNotFoundException
	 */
	function copy_from($src_id, array $excludes = [], array $includes = [])
	{
		$copy_keys = [];
		foreach($this->columns() as $col){
			if( ! in_array($col, $excludes) ){
				$copy_keys[] = $col;
			}
		}
		if( $copy_keys ){
			$table = static::table();
			$pkey  = static::primary_key();
			$q     = "update {$table} set";
			foreach($copy_keys as $key){
				$q .= " {$key}=src.{$key}";
			}
			$q .= " from {$table} as src where {$table}.{$pkey}=src.{$pkey} and {$table}.{$pkey}={$src_id}";
			DB::query($q)->execute();
			$this->reload();
		}

		return $this;
	}

	/**
	 * データを再ロードする
	 *
	 * @see Model::after_load()
	 * @throws MkException
	 * @throws RecordNotFoundException
	 * @return Model
	 */
	function reload(array $ignore_conditions = null)
	{
		$query    = $this->_build_select_query();
		$id_field = static::primary_key();
		$r        = $query->where($id_field, $this->$id_field)->ignore_conditions($ignore_conditions)->get_one();
		if( $r === null ){
			throw new RecordNotFoundException;
		}

		// 取得したデータをoriginalに入れ、dataは削除する
		$this->_original = [];
		foreach($r as $key => $value){
			$this->_original[$key] = $value;
			if( array_key_exists($key, $this->_data) ){
				unset($this->_data[$key]);
			}
		}

		$this->after_load();

		return $this;
	}

	function as_array($array_key = null)
	{
		return array_merge($this->_original, $this->_data);
	}

	/**
	 * 指定されたカラムまたはデータのどれかが変更されたかをチェックする
	 *
	 * @param null|string $column
	 *
	 * @return bool
	 */
	function is_changed($column = null)
	{
		if( $column && array_key_exists($column, $this->_data) ){
			return true;
		}
		if( $column === null && count($this->_data) ){
			return true;
		}
	}

	/**
	 * originalとのdiffを取得する
	 *
	 * @return array
	 */
	function get_diff()
	{
		$diff = [];
		foreach($this->_data as $key => $value){
			if( isset($this->_original[$key]) && $this->_original[$key] === $value ){
				continue;
			}
			$diff[0][$key] = array_key_exists($key, $this->_original) ? $this->_original[$key] : null;
			$diff[1][$key] = $value;
		}

		return $diff;
	}

	function delete()
	{
		$primary_key = static::primary_key();
		if( ! $primary_key ){
			throw new MkException('primary key required');
		}
		if( ! $this->get($primary_key) ){
			throw new MkException('empty primary key');
		}

		$this->before_delete();
		$r = DB::delete()->from($this->table())->where($primary_key, $this->get($primary_key))->execute();
		unset($this->$primary_key);
		$this->after_delete();

		return $r->get_affected_rows();
	}

	protected function before_delete(){ }

	protected function after_delete(){ }

	/*
	public static function instance_from_query_data()
	{
		$db_query = forward_static_call_array('DB::query',func_get_args());
		return $db_query->set_fetch_as(get_called_class())->execute();
	}
	 * 
	 */

	function drop_isnew_flag()
	{
		$this->_is_new = false;

		return $this;
	}

	static function form()
	{
		if( property_exists(get_called_class(), 'form') ){
			return static::$form;
		}
		else{
			return null;
		}
	}

	/**
	 * このModelのデータを取得する際のselectクエリを得る
	 *
	 * @return Database_Query
	 */
	public function get_select_query()
	{
		$model_query = static::_build_select_query();    // Model_Queryにjoinやadd_fieldを加える
		return $model_query->get_query();                // Database_Queryを得る
	}

	public function offsetSet($offset, $value)
	{
		return $this->set($offset, $value);
	}

	public function offsetExists($offset)
	{
		return $this->is_exist($offset);
	}

	function is_exist($name)
	{
		return (array_key_exists($name, $this->_data) || array_key_exists($name, $this->_original));
	}

	public function offsetUnset($offset)
	{
		$this->_unset($offset);
	}

	public function offsetGet($offset)
	{
		return $this->get($offset);
	}

	function rewind()
	{
		$this->_iter_keylist = $this->keys();
		$this->_iter_curkey  = 0;
	}

	function keys()
	{
		return array_merge(array_keys($this->_original), array_keys($this->_data));
	}

	function current()
	{
		return $this->get($this->key());
	}

	function key()
	{
		return $this->_iter_keylist[$this->_iter_curkey];
	}

	function next()
	{
		if( $this->valid() ){
			$this->_iter_curkey++;
		}
	}

	function valid()
	{
		return ($this->_iter_curkey < $this->count());
	}

	function count()
	{
		return count($this->_iter_keylist);
	}
}
