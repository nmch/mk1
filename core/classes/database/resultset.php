<?php

/**
 * DB返り値ハンドラ
 */
class Database_Resultset implements Iterator, Countable, ArrayAccess
{
	private $result_resource;
	private $position;
	private $rows;
	private $fetch_as              = [];
	private $fields;
	private $fields_hashed_by_name = [];
	private $query;

	function __construct($result)
	{
		$this->result_resource = $result;
		$this->rows            = pg_num_rows($result);
		$this->position        = 0;

		$num_of_fields = pg_num_fields($result);
		for($c = 0; $c < $num_of_fields; $c++){
			$field = [
				'num'     => $c,
				'table'   => pg_field_table($result, $c),
				'type'    => pg_field_type($result, $c),
				'name'    => pg_field_name($result, $c),
				'prtlen'  => pg_field_prtlen($result, $c),
				'is_null' => pg_field_is_null($result, $c),
			];

			$this->fields[]                              = $field;
			$this->fields_hashed_by_name[$field['name']] = $field;
		}

		//Log::coredebug($fields_hashed_by_name);

		return $this;
	}

	/**
	 * 影響を受けた行数を取得する
	 *
	 * @return int
	 */
	function get_affected_rows()
	{
		return pg_affected_rows($this->result_resource);
	}

	/**
	 * クエリオブジェクトを取得する
	 *
	 * @return Database_Query
	 */
	function get_query()
	{
		return $this->query;
	}

	/**
	 * クエリオブジェクトを設定する
	 *
	 * @param Database_Query $query クエリオブジェクト
	 *
	 * @return Database_Query
	 */
	function set_query(Database_Query $query)
	{
		return $this->query = $query;
	}

	function fieldinfo()
	{
		return $this->fields;
	}

	function get_first($column = null)
	{
		return $this->get($column, 0);
	}

	function get($column = null, $position = null)
	{
		$row = $this->fetch(null, $position);
		if( $column ){
			return isset($row[$column]) ? $row[$column] : null;
		}
		else{
			return $row;
		}
	}

	function fetch($fetch_as = null, $position = null, $forward = false)
	{
		//Log::coredebug("[db] fetch($fetch_as, $position, $forward)");
		if( $this->rows == 0 ){
			return null;
		}
		if( $position === null ){
			$position = $this->position;
		}
		if( ! $this->offsetExists($position) ){
			throw new OutOfRangeException('invalid offset ' . $position);
		}

		$fetch_as = is_null($fetch_as) ? $this->fetch_as : $fetch_as;
		//Log::coredebug("[db] fetch as ",$fetch_as,$position);
		if( is_string($fetch_as) ){
			$data = pg_fetch_object($this->result_resource, $position, $fetch_as, ['deferred_init' => true]);
			if( $data instanceof Model ){
				$data->drop_isnew_flag();
			}
			//Log::coredebug("pg_fetch_object",$data);
		}
		else{
			$data = pg_fetch_assoc($this->result_resource, $position);
		}
		if( $forward ){
			$this->next();
		}
		$data = $this->correct_data($data);

		return $data;
	}

	public function offsetExists($offset)
	{
		return is_numeric($offset) && ($offset < $this->rows);
	}

	function next()
	{
		$this->position++;

		return $this;
	}

	/**
	 * データを型にそって正しい形式へフォーマットする
	 *
	 * インスタンス生成時に取得したフィールドの型データと、データ本体とを見比べて
	 * 正しい表記へ書き換え、返します。
	 * Booleanの表記('t'/'f' → true/false)、JSON等が対象です。
	 *
	 * @param mixed $data
	 *
	 * @return mixed
	 */
	protected function correct_data($data)
	{
		foreach($this->fields_hashed_by_name as $name => $field){
			try {
				if( is_object($data) ){
					$data->set($name, static::correct_value($data->$name, $field['type']), true);
				}
				else{
					if( is_array($data) ){
						$data[$name] = static::correct_value($data[$name], $field['type']);
					}
				}
			} catch(Exception $e){
				Log::coredebug("error at correct_data", $name, $field, $e);
				throw $e;
			}
		}

		return $data;
	}

	protected static function correct_value($value, $type)
	{
		$type = Database_Type::get($type);
		if( ! $type ){
			throw new MkException('invalid type');
		}

//		Log::coredebug("correct_value : ",$value,$type);

		try {
			switch($type['typcategory']){
				case 'N':
					if( is_string($value) && strpos($type['typname'], 'int') === 0 ){
						$value = intval($value);
					}
//				Log::coredebug("typcategory=N ".gettype($value), $value,$type);
					break;
				case 'B':
					$value = ($value === 't' ? true : ($value === 'f' ? false : null));
					/*
					if( ! $value ){
						$value = NULL;
					}
					else{
						if($value === 't')
							$value = true;
						else
							$value = false;
					}
					*/
					break;
				case 'A':
					if( $value ){
						$delimiter = $type['typdelim'];
						if( is_array($value) ){
							// nop
						}
						elseif( $value === '{}' ){
							$value = [];
						}
						else{
							$value = array_map(function ($str) {
								return stripslashes($str);
							}, str_getcsv(trim($value, '{}'), $delimiter, '"', '\\')
							);
						}
					}
					break;
				case 'U':
					if( $type['typname'] === 'json' || $type['typname'] === 'jsonb' ){
						$value = json_decode($value, true);
					}
					break;
			}
		} catch(Exception $e){
			Log::coredebug("error at correct value", $type, $value, $e);
			throw $e;
		}

		//Log::coredebug("correct value [$value] as $type");
		return $value;
	}

	function get_last($column = null)
	{
		return $this->get($column, $this->rows ? ($this->rows - 1) : null);
	}

	/**
	 * 結果データを1レコード1オブジェクトの形式で格納した配列を返す
	 *
	 * correct_data()を参照時まで遅延できるが、データ行数ぶんpg_result()が実行される。
	 *
	 * @param string|null $array_key
	 *
	 * @return array
	 */
	function as_object_array($array_key = null)
	{
		$list = [];
		foreach($this as $item){
			if( $array_key ){
				$list[$item->$array_key] = $item;
			}
			else{
				$list[] = $item;
			}
		}

		return $list;
	}

	/**
	 * 結果データを1レコード1配列の形式で格納した配列を返す
	 *
	 * 全カラムに対してcorrect_data()が実行されるが、データ取得はpg_fetch_all()で一括して行う。
	 *
	 * @param bool        $correct_values
	 * @param string|null $array_key
	 *
	 * @return array
	 */
	function as_array($correct_values = false, $array_key = null)
	{
		// Database_Type::retrieve()から、加工なしで返ることを期待して呼ばれているので注意

		$data = pg_fetch_all($this->result_resource);
		if( ! $data ){
			$data = [];
		}
		if( $correct_values ){
			foreach($data as $key => $item){
				$data[$key] = $this->correct_data($item);
			}
		}
		if( $array_key ){
			$new_data = [];
			foreach($data as $item){
				$new_data[Arr::get($item, $array_key)] = $item;
			}
			unset($data);
			$data = $new_data;
		}

		return $data;
	}

	function get_fetch_as()
	{
		return $this->fetch_as;
	}

	function set_fetch_as($fetch_as)
	{
		$this->fetch_as = $fetch_as;

		return $this;
	}

	function rewind()
	{
		pg_result_seek($this->result_resource, 0);
		$this->position = 0;
	}

	function current()
	{
		return $this->fetch();
	}

	function key()
	{
		return $this->position;
	}

	function valid()
	{
		return $this->offsetExists($this->position);
	}

	function count()
	{
		return $this->rows;
	}

	function seek($position)
	{
		if( $this->offsetExists($position) ){
			$this->position = $position;
		}

		return $this;
	}

	public function offsetSet($offset, $value)
	{
		// nop
	}

	public function offsetUnset($offset)
	{
		// nop
	}

	public function offsetGet($offset)
	{
		return $this->fetch(null, $offset);
	}
}
