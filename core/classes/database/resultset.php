<?
class Database_Resultset implements Iterator,Countable,ArrayAccess
{
	private $result_resource;
	private $position;
	private $rows;
	private $fetch_as = array();
	private $fields;
	private $fields_hashed_by_name = array();
	private $query;
	
	function __construct($result)
	{
		$this->result_resource = $result;
		$this->rows = pg_num_rows($result);
		$this->position = 0;
		
		$num_of_fields = pg_num_fields($result);
		for($c = 0;$c < $num_of_fields;$c++){
			$field = array(
				'num' => $c,
				'table' => pg_field_table($result,$c),
				'type' => pg_field_type($result,$c),
				'name' => pg_field_name($result,$c),
				'prtlen' => pg_field_prtlen($result,$c),
				'is_null' => pg_field_is_null($result,$c),
			);
			
			$this->fields[] = $field;
			$this->fields_hashed_by_name[$field['name']] = $field;
		}
		//Log::coredebug($fields_hashed_by_name);
		
		return $this;
	}
	function get_affected_rows()
	{
		return pg_affected_rows($this->result_resource);
	}
	function get_query()
	{
		return $this->query;
	}
	function set_query($query)
	{
		return $this->query = $query;;
	}
	function fieldinfo()
	{
		return $this->fields;
	}
	function get($column = NULL,$position = NULL)
	{
		$row = $this->fetch(NULL,$position);
		if($column)
			return isset($row[$column]) ? $row[$column] : NULL;
		else
			return $row;
	}
	function get_first($column = NULL)
	{
		return $this->get($column,0);
	}
	function get_last($column = NULL)
	{
		return $this->get($column,$this->rows ? ($this->rows - 1) : NULL);
	}
	function as_object_array($array_key = NULL)
	{
		$list = [];
		foreach($this as $item){
			if($array_key)
				$list[$item->$array_key] = $item;
			else
				$list[] = $item;
		}
		return $list;
	}
	function as_array($correct_values = false, $array_key = NULL)
	{
		// Database_Type::retrieve()から、加工なしで返ることを期待して呼ばれているので注意
		
		$data = pg_fetch_all($this->result_resource);
		if( ! $data )
			$data = array();
		if($correct_values){
			foreach($data as $key => $item){
				$data[$key] = $this->correct_data($item);
			}
		}
		if($array_key){
			$new_data = array();
			foreach($data as $item){
				$new_data[ Arr::get($item,$array_key) ] = $item;
			}
			unset($data);
			$data = $new_data;
		}
		return $data;
	}
	function set_fetch_as($fetch_as)
	{
		$this->fetch_as = $fetch_as;
		return $this;
	}
	
	function fetch($fetch_as = NULL,$position = NULL,$forward = false)
	{
		//Log::coredebug("[db] fetch($fetch_as, $position, $forward)");
		if($this->rows == 0)
			return NULL;
		if($position === NULL)
			$position = $this->position;
		if( ! $this->offsetExists($position) )
			throw new OutOfRangeException('invalid offset '.$position);
		
		$fetch_as = is_null($fetch_as) ? $this->fetch_as : $fetch_as;
		//Log::coredebug("[db] fetch as ",$fetch_as,$position);
		if(is_string($fetch_as)){
			$data = pg_fetch_object($this->result_resource,$position,$fetch_as,array('deferred_init' => true));
			if($data instanceof Model)
				$data->drop_isnew_flag();
			//Log::coredebug("pg_fetch_object",$data);
		}
		else
			$data = pg_fetch_assoc($this->result_resource,$position);
		if($forward)
			$this->next();
		$data = $this->correct_data($data);
		return $data;
	}
	/**
	 * データを型にそって正しい形式へフォーマットする
	 * 
	 * インスタンス生成時に取得したフィールドの型データと、データ本体とを見比べて
	 * 正しい表記へ書き換え、返します。
	 * 主にBooleanの表記('t'/'f' → true/false)が対象です。
	 * 
	 * @param type $data
	 */
	protected function correct_data($data)
	{
		foreach($this->fields_hashed_by_name as $name => $field){
			if(is_object($data)){
				$data->set($name,static::correct_value($data->$name,$field['type']),true);
			}
			else if(is_array($data))
				$data[$name] = static::correct_value($data[$name],$field['type']);
		}
		return $data;
	}
	protected static function correct_value($value,$type)
	{
		$type = Database_Type::get($type);
		if( ! $type )
			throw new MkException('invalid type');
		
		//Log::coredebug("correct_value : $value",$type);
		switch($type['typcategory']){
			case 'B':
				if( ! $value )
					$value = NULL;
				else{
					if($value == 't')
						$value = true;
					else
						$value = false;
				}
				break;
			case 'A':
				if($value){
					$delimiter = $type['typdelim'];
					if($value == '{}')
						$value = array();
					else
						$value = array_map(function($str){ return stripslashes($str); },str_getcsv(trim($value,'{}'), $delimiter, '"', '\\'));
				}
				break;
		}
		//Log::coredebug("correct value [$value] as $type");
		return $value;
	}
			
	function rewind() {
		pg_result_seek($this->result_resource,0);
		$this->position = 0;
	}
	function current() {
		return $this->fetch();
	}
	function key() {
		return $this->position;
	}
	function next() {
		$this->position++;
		return $this;
	}
	function valid() {
		return $this->offsetExists($this->position);
	}
	function count()
	{
		return $this->rows;
	}
	function seek($position)
	{
		if($this->offsetExists($position))
			$this->position = $position;
		return $this;
	}
    public function offsetSet($offset, $value) {
		// nop
    }
    public function offsetExists($offset) {
		return is_numeric($offset) && ($offset < $this->rows);
    }
    public function offsetUnset($offset) {
        // nop
    }
    public function offsetGet($offset) {
        return $this->fetch(NULL,$offset);
    }
}
