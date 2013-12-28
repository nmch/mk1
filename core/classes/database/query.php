<?
class Database_Query
{
	protected $_sql;
	protected $_parameters = array();
	protected $_parameters_index = 1;
	protected $fetch_as = array();
	protected $affected_rows = NULL;
	//protected $result = NULL;
	
	protected $_query_type = NULL;
	protected $_query_columns = array();
	protected $_query_where = array();
	protected $_query_from = array();
	protected $_query_with= array();
	protected $_query_join= array();
	protected $_query_values = array();
	protected $_query_orderby = array();
	protected $_query_limit = NULL;
	protected $_query_offset = NULL;
	protected $_query_distinct = FALSE;
	protected $_query_returning = array();
	protected $_query_primarykey = array();
	
	
	public function __construct($sql = NULL,$parameters = array())
	{
		$this->_sql = $sql;
		$this->_parameters = $parameters;
	}
	/*
	public function param($param, $value)
	{
		$this->_parameters[$param] = $value;
		return $this;
	}
	public function bind($param, & $var)
	{
		$this->_parameters[$param] =& $var;
		return $this;
	}
	public function parameters(array $params)
	{
		$this->_parameters = $params + $this->_parameters;
		return $this;
	}
	*/
	public function execute($db = null)
	{
		if ( ! is_object($db) )
			$db = Database_Connection::instance($db);
		if($this->_query_type)
			$this->compile();
		if( ! $this->_sql )
			throw new MkException('sql compile failed');
		//Log::coredebug("[db] SQL = ".$this->_sql);
		try {
			/*
			$this->result = $db->query($this->_sql, $this->_parameters)->set_fetch_as($this->fetch_as);
			if($this->result instanceof Database_Resultset)
				$this->result->set_query($this);
			*/
			$result = $db->query($this->_sql, $this->_parameters)->set_fetch_as($this->fetch_as);
			if($result instanceof Database_Resultset){
				$result->set_query($this);
				$this->affected_rows = $result->get_affected_rows();
			}
			return $result;
		} catch(Exception $e){
			$message = $e->getMessage();
			$new_expception = new DatabaseQueryError($message,$e->getCode(),$e);
			Log::error($message);
			throw $new_expception;
		}
	}
	function get_affected_rows()
	{
		/*
		if($this->result instanceof Database_Resultset)
			return $this->result->get_affected_rows();
		else
			return NULL;
		*/
		return $this->affected_rows;
	}
	function set_fetch_as($fetch_as)
	{
		$this->fetch_as = $fetch_as;
		return $this;
	}
	public function set_sql($sql)
	{
		if( ! is_string($sql) ){
			throw new MkException('sql must be a string');
		}
		$this->_sql = $sql;
		return $this;
	}
	public function get_sql($with_parameters = false)
	{
		if($this->_query_type){
			$this->compile();
		}
		
		if($with_parameters){
			return [$this->_sql,$this->_parameters];
		}
		else{
			return $this->_sql;
		}
	}
	public function compile()
	{
		if(empty($this->_query_type)){
			throw new MkException('empty query type');
		}
		$this->clear_parameter_index();
		$this->_sql = $this->{'compile_'.$this->_query_type}();
		return $this;
	}
	public function compile_update()
	{
		$from = is_array($this->_query_from) ? reset($this->_query_from) : $this->_query_from;
		if( ! $from )
			throw new Exception('table required');
		if( ! $this->_query_values || ! is_array($this->_query_values) )
			throw new MkException('values required');
		
		$sql = "UPDATE $from SET ";
		$ary = array();
		foreach($this->_query_values as $key => $value){
			if($value instanceof Database_Expression)
				$ary[] = "$key=".(string)$value;
			else
				$ary[] = "$key=$".$this->parameter($value);
		}
		$sql .= implode(',',$ary);
		$where = $this->build_where();
		if($where)
			$sql .= " WHERE $where";
		if($this->_query_returning)
			$sql .= " RETURNING ".(is_array($this->_query_returning) ? implode(',',$this->_query_returning) : $this->_query_returning);
		
		return $sql;
	}
	public function compile_insert()
	{
		$table = is_array($this->_query_from) ? reset($this->_query_from) : $this->_query_from;
		if( ! $table )
			throw new Exception('table required');
		
		$sql = "INSERT INTO $table ";
		if( ! $this->_query_values )
			$sql .= "DEFAULT VALUES";
		else{
			$sql .= '('.implode(',',array_keys($this->_query_values)).')';
			$ary = array();
			foreach($this->_query_values as $value)
				$ary[] = '$'.$this->parameter($value);
			$sql .= ' VALUES ('.implode(',',$ary).')';
		}
		
		$where = $this->build_where();
		if($where)
			$sql .= " WHERE $where";
		
		if($this->_query_returning)
			$sql .= " RETURNING ".(is_array($this->_query_returning) ? implode(',',$this->_query_returning) : $this->_query_returning);
		
		return $sql;
	}
	public function compile_select()
	{
		//Log::coredebug("_query_columns=",$this->_query_columns);
		$sql = '';
		if($this->_query_with){
			$sql_with = [];
			foreach($this->_query_with as $with){
				$sql_with[] = Arr::get($with,'name').' AS ('.Arr::get($with,'query').')';
			}
			$sql .= "WITH ".implode(',',$sql_with)." ";
		}
		$sql .= "SELECT ";
		if($this->_query_distinct)
			$sql .= " DISTINCT ";
		$sql .= $this->_query_columns ? implode(',',$this->_query_columns) : '*';
		$sql .= " FROM ".implode(',',$this->_query_from);
		$where = $this->build_where();
		if($this->_query_join)
			$sql .= ' '.implode(' ',$this->_query_join);
		if($where)
			$sql .= " WHERE $where";
		if($this->_query_orderby)
			$sql .= " ORDER BY ".implode(',',array_map(function($ary){ return implode(' ',$ary); },$this->_query_orderby));
		if($this->_query_limit)
			$sql .= " LIMIT $this->_query_limit";
		if($this->_query_offset)
			$sql .= " OFFSET $this->_query_offset";
		
		//Log::coredebug("[db query] sql=$sql");
		return $sql;
	}
	public function compile_delete()
	{
		$sql = "DELETE ";
		$sql .= " FROM ".implode(',',$this->_query_from);
		$where = $this->build_where();
		if($where)
			$sql .= " WHERE $where";
		if($this->_query_limit)
			$sql .= " LIMIT $this->_query_limit";
		return $sql;
	}
	/**
	 * where条件が設定されているか
	 *
	 * @return boolean
	 */
	function condition_where_exists()
	{
		//Log::coredebug("condition_where_exists=",$this->_query_where,!empty($this->_query_where));
		return ( ! empty($this->_query_where) );
	}
	function build_where()
	{
		//Log::coredebug($this->_query_where,$this->_query_values);
		$last_condition = NULL;

		$sql = '';
		foreach ($this->_query_where as $group)
		{
			// Process groups of conditions
			foreach ($group as $logic => $condition)
			{
				//Log::coredebug("$logic = ",$condition);
				if ($condition === '(')
				{
					if ( ! empty($sql) AND $last_condition !== '(')
					{
						// Include logic operator
						$sql .= ' '.$logic.' ';
					}

					$sql .= '(';
				}
				elseif ($condition === ')')
				{
					$sql .= ')';
				}
				else
				{
					if ( ! empty($sql) AND $last_condition !== '(')
					{
						// Add the logic operator
						$sql .= ' '.$logic.' ';
					}

					// Split the condition
					list($column, $op, $value) = $condition;
					//Log::coredebug($column,$op,$value);
					
					if ($value === NULL)
					{
						if ($op === '=')
						{
							// Convert "val = NULL" to "val IS NULL"
							$op = 'IS';
						}
						elseif ($op === '!=' || $op === '<>')
						{
							// Convert "val != NULL" to "valu IS NOT NULL"
							$op = 'IS NOT';
						}
					}

					if (is_bool($value))
					{
						if ($op === '=')
						{
							$op = 'IS';
						}
						elseif ($op === '!=' || $op === '<>')
						{
							$op = 'IS NOT';
						}
					}

					// Database operators are always uppercase
					$op = strtoupper($op);
					
					/*
					if (($op === 'BETWEEN' OR $op === 'NOT BETWEEN') AND is_array($value))
					{
						// BETWEEN always has exactly two arguments
						list($min, $max) = $value;

						if (is_string($min) AND array_key_exists($min, $this->_parameters))
						{
							// Set the parameter as the minimum
							$min = $this->_parameters[$min];
						}

						if (is_string($max) AND array_key_exists($max, $this->_parameters))
						{
							// Set the parameter as the maximum
							$max = $this->_parameters[$max];
						}

						// Quote the min and max value
						$value = $db->quote($min).' AND '.$db->quote($max);
					}
					*/
					if($value instanceof Database_Expression)
						$sql .= $column.' '.$op.' '.(string)$value;
					else if($value === NULL)
						$sql .= $column.' '.$op.' NULL';
					else if($value === TRUE)
						$sql .= $column.' '.$op.' TRUE';
					else if($value === FALSE)
						$sql .= $column.' '.$op.' FALSE';
					else
						$sql .= $column.' '.$op.' $'.$this->parameter($value);
				}

				$last_condition = $condition;
			}
		}

		//Log::coredebug("sql=$sql",$this->_parameters);
		return $sql;
	}
	function parameter($value)
	{
		$this->_parameters[$this->_parameters_index - 1] = $value;
		return $this->_parameters_index++;
	}
	function clear_parameter_index()
	{
		$this->_parameters_index = 1;
		return $this;
	}
	function clear_parameter()
	{
		$this->_parameters = array();
		$this->_parameters_index = 1;
		return $this;
	}
	
	function select($columns = array())
	{
		if( ! is_array($columns) )
			$columns = func_get_args();
		$this->_query_type = 'SELECT';
		//$this->_query_columns = $columns;
		//$this->_query_columns = array_merge($this->_query_columns,$columns);
		foreach($columns as $col){
			$col = trim($col);
			if( ! in_array($col,$this->_query_columns) ){
				$this->_query_columns[] = $col;
			}
		}
		//Log::coredebug("select() _query_columns=",$columns,$this->_query_columns);
		//echo "<PRE>"; debug_print_backtrace(); echo "</PRE>";
		return $this;
	}
	function clear_query_type()
	{
		$this->_query_type = NULL;
		return $this;
	}
	function clear_select()
	{
		$this->_query_columns = array();
		return $this;
	}
	function clear_from()
	{
		$this->_query_from = array();
		return $this;
	}
	
	function update($table)
	{
		$this->_query_type = 'UPDATE';
		$this->from($table);
		
		return $this;
	}
	function insert($table)
	{
		$this->_query_type = 'INSERT';
		$this->from($table);
		
		return $this;
	}
	function delete()
	{
		$this->_query_type = 'DELETE';
		return $this;
	}
	function from($from)
	{
		$this->_query_from[] = $from;
		return $this;
	}
	function set(array $values)
	{
		return $this->values($values);
	}
	function with($with,$with_query = NULL)
	{
		if(is_array($with))
			$this->_query_with = array_merge($this->_query_with,$with);
		else
			$this->_query_with[] = ['name' => $with, 'query' => $with_query];
		return $this;
	}
	function values(array $values)
	{
		$this->_query_values = array_merge($this->_query_values,$values);
		return $this;
	}
	public function where()
	{
		return call_user_func_array(array($this, 'and_where'), func_get_args());
	}
	public function and_where($column, $op = null, $value = null)
	{
		if($column instanceof \Closure)
		{
			$this->and_where_open();
			$column($this);
			$this->and_where_close();
			return $this;
		}
		if (is_array($column))
		{
			foreach ($column as $key => $val)
			{
				if (is_array($val))
				{
					$this->and_where($val[0], $val[1], $val[2]);
				}
				else
				{
					$this->and_where($key, '=', $val);
				}
			}
		}
		else
		{
			if(func_num_args() === 2)
			{
				$value = $op;
				$op = '=';
			}
			$this->_query_where[] = array('AND' => array($column, $op, $value));
		}

		return $this;
	}
	public function or_where($column, $op = null, $value = null)
	{
		if($column instanceof \Closure)
		{
			$this->or_where_open();
			$column($this);
			$this->or_where_close();
			return $this;
		}

		if (is_array($column))
		{
			foreach ($column as $key => $val)
			{
				if (is_array($val))
				{
					$this->or_where($val[0], $val[1], $val[2]);
				}
				else
				{
					$this->or_where($key, '=', $val);
				}
			}
		}
		else
		{
			if(func_num_args() === 2)
			{
				$value = $op;
				$op = '=';
			}
			$this->_query_where[] = array('OR' => array($column, $op, $value));
		}
		return $this;
	}
	public function where_open()
	{
		return $this->and_where_open();
	}
	public function and_where_open()
	{
		$this->_query_where[] = array('AND' => '(');

		return $this;
	}
	public function or_where_open()
	{
		$this->_query_where[] = array('OR' => '(');

		return $this;
	}
	public function where_close()
	{
		return $this->and_where_close();
	}
	public function and_where_close()
	{
		$this->_query_where[] = array('AND' => ')');

		return $this;
	}
	public function or_where_close()
	{
		$this->_query_where[] = array('OR' => ')');

		return $this;
	}
	/*
	function where()
	{
		$column = NULL;
		$op = NULL;
		$value = NULL;
		
		$args = func_get_args();
		if(func_num_args() == 2){
			$column = array_shift($args);
			$value = array_shift($args);
		}
		else if(func_num_args() == 3){
			$column = array_shift($args);
			$op = array_shift($args);
			$value = array_shift($args);
		}
		else
			throw new MkException('invalid where options');
		
		if($value === NULL){
			if( $op == '=' || ! $op )
				$op = 'is';
			if( $op == '!=' || $op == '<>' )
				$op = 'is not';
		}
		else{
			if( ! $op )
				$op = '=';
		}
		$this->_query_query_where[] = "$column $op ?";
		$this->_query_values[] = $value;
		
		return $this;
	}
	*/
	function order_by($column, $direction = null)
	{
		if($column){
			if( ! is_array($column) ){
				$this->order_by(array((string)$column => $direction));
			}
			else{
				foreach($column as $_column => $_direction){
					$this->_query_orderby[] = array($_column, $_direction);
				}
			}
		}
		return $this;
	}
	function clear_order_by()
	{
		$this->_query_orderby = array();
		return $this;
	}
	
	function returning($column)
	{
		if(is_array($column))
			$this->_query_returning = array_merge($this->_query_returning,$column);
		else
			$this->_query_returning[] = $column;
		
		return $this;
	}
	function join($join_str)
	{
		$this->_query_join[] = $join_str;
		return $this;
	}
	function limit($limit)
	{
		$this->_query_limit = (int) $limit;
		return $this;
	}
	function offset($offset)
	{
		$this->_query_offset = (int) $offset;
		return $this;
	}
	function distinct($flag)
	{
		$this->_query_distinct = (boolean)$flag;
		return $this;
	}
}
