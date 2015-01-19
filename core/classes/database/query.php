<?php

/**
 * Class Database_Query
 */
class Database_Query
{
	protected $_sql;
	protected $_parameters       = [];
	protected $_parameters_index = 1;
	protected $fetch_as          = [];
	protected $affected_rows     = NULL;
	//protected $result = NULL;

	protected $_query_type       = NULL;
	protected $_query_columns    = [];
	protected $_query_where      = [];
	protected $_query_from       = [];
	protected $_query_with       = [];
	protected $_query_join       = [];
	protected $_query_values     = [];
	protected $_query_orderby    = [];
	protected $_query_limit      = NULL;
	protected $_query_offset     = NULL;
	protected $_query_distinct   = FALSE;
	protected $_query_returning  = [];
	protected $_query_primarykey = [];


	public function __construct($sql = NULL, $parameters = [])
	{
		$this->_sql        = $sql;
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

	/**
	 * クエリを実行する
	 *
	 * @param null|Database_Connection $db
	 *
	 * @throws DatabaseQueryError
	 * @throws MkException
	 * @return Database_Resultset
	 */
	public function execute($db = null)
	{
		if( ! is_object($db) ){
			$db = Database_Connection::instance($db);
		}
		if( $this->_query_type ){
			$this->compile();
		}
		if( ! $this->_sql ){
			throw new MkException('sql compile failed');
		}
		//Log::coredebug("[db] SQL = ".$this->_sql);
		try {
			/*
			$this->result = $db->query($this->_sql, $this->_parameters)->set_fetch_as($this->fetch_as);
			if($this->result instanceof Database_Resultset)
				$this->result->set_query($this);
			*/
			$result = $db->query($this->_sql, $this->_parameters)->set_fetch_as($this->fetch_as);
			if( $result instanceof Database_Resultset ){
				$result->set_query($this);
				$this->affected_rows = $result->get_affected_rows();
			}

			return $result;
		} catch(Exception $e){
			$message        = $e->getMessage();
			$new_expception = new DatabaseQueryError($message, $e->getCode(), $e);
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

	/**
	 * @return Database_Query
	 */
	function set_fetch_as($fetch_as)
	{
		$this->fetch_as = $fetch_as;

		return $this;
	}

	function get_fetch_as()
	{
		return $this->fetch_as;
	}

	/**
	 * @param $sql
	 *
	 * @return Database_Query
	 * @throws MkException
	 */
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
		if( $this->_query_type ){
			$this->compile();
		}

		if( $with_parameters ){
			return [$this->_sql, $this->_parameters];
		}
		else{
			return $this->_sql;
		}
	}

	/**
	 * @return Database_Query
	 * @throws MkException
	 */
	public function compile()
	{
		if( empty($this->_query_type) ){
			throw new MkException('empty query type');
		}
		$this->clear_parameter_index();
		$this->_sql = $this->{'compile_' . $this->_query_type}();

		return $this;
	}

	public function compile_update()
	{
		$from = is_array($this->_query_from) ? reset($this->_query_from) : $this->_query_from;
		if( ! $from ){
			throw new Exception('table required');
		}
		if( ! $this->_query_values || ! is_array($this->_query_values) ){
			throw new MkException('values required');
		}

		$sql = "UPDATE $from SET ";
		$ary = [];
		foreach($this->_query_values as $key => $value){
			if( $value instanceof Database_Expression ){
				$ary[] = "$key=" . (string)$value;
			}
			else{
				$ary[] = "$key=$" . $this->parameter($value);
			}
		}
		$sql .= implode(',', $ary);
		$where = $this->build_where();
		if( $where ){
			$sql .= " WHERE $where";
		}
		if( $this->_query_returning ){
			$sql .= " RETURNING " . (is_array($this->_query_returning) ? implode(',', $this->_query_returning) : $this->_query_returning);
		}

		return $sql;
	}

	public function compile_insert()
	{
		$table = is_array($this->_query_from) ? reset($this->_query_from) : $this->_query_from;
		if( ! $table ){
			throw new Exception('table required');
		}

		$sql = "INSERT INTO $table ";
		if( ! $this->_query_values ){
			$sql .= "DEFAULT VALUES";
		}
		else{
			$sql .= '(' . implode(',', array_keys($this->_query_values)) . ')';
			$ary = [];
			foreach($this->_query_values as $value){
				$ary[] = '$' . $this->parameter($value);
			}
			$sql .= ' VALUES (' . implode(',', $ary) . ')';
		}

		$where = $this->build_where();
		if( $where ){
			$sql .= " WHERE $where";
		}

		if( $this->_query_returning ){
			$sql .= " RETURNING " . (is_array($this->_query_returning) ? implode(',', $this->_query_returning) : $this->_query_returning);
		}

		return $sql;
	}

	public function compile_select()
	{
		//Log::coredebug("_query_columns=",$this->_query_columns);
		$sql = '';
		if( $this->_query_with ){
			$sql_with = [];
			foreach($this->_query_with as $with){
				$sql_with[] = Arr::get($with, 'name') . ' AS (' . Arr::get($with, 'query') . ')';
			}
			$sql .= "WITH " . implode(',', $sql_with) . " ";
		}
		$sql .= "SELECT ";
		if( $this->_query_distinct ){
			$sql .= " DISTINCT ";
		}
		$sql .= $this->_query_columns ? implode(',', $this->_query_columns) : '*';
		$sql .= " FROM " . implode(',', $this->_query_from);
		$where = $this->build_where();
		if( $this->_query_join ){
			$sql .= ' ' . implode(' ', $this->_query_join);
		}
		if( $where ){
			$sql .= " WHERE $where";
		}
		if( $this->_query_orderby ){
			$sql .= " ORDER BY " . implode(',', array_map(function ($ary) {
							return implode(' ', $ary);
						}, $this->_query_orderby
					)
				);
		}
		if( $this->_query_limit ){
			$sql .= " LIMIT $this->_query_limit";
		}
		if( $this->_query_offset ){
			$sql .= " OFFSET $this->_query_offset";
		}

		//Log::coredebug("[db query] sql=$sql");
		return $sql;
	}

	public function compile_delete()
	{
		$sql = "DELETE ";
		$sql .= " FROM " . implode(',', $this->_query_from);
		$where = $this->build_where();
		if( $where ){
			$sql .= " WHERE $where";
		}
		if( $this->_query_limit ){
			$sql .= " LIMIT $this->_query_limit";
		}
		if( $this->_query_returning ){
			$sql .= " RETURNING " . (is_array($this->_query_returning) ? implode(',', $this->_query_returning) : $this->_query_returning);
		}

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
		return ( ! empty($this->_query_where));
	}

	function build_where()
	{
		//Log::coredebug($this->_query_where,$this->_query_values);
		$last_condition = NULL;

		$sql = '';
		foreach($this->_query_where as $group){
			// Process groups of conditions
			foreach($group as $logic => $condition){
				//Log::coredebug("$logic = ",$condition);
				//echo "[$logic = "; var_export($condition); echo "]\n";
				if( $condition === '(' ){
					if( ! empty($sql) AND $last_condition !== '(' ){
						// Include logic operator
						$sql .= ' ' . $logic . ' ';
					}

					$sql .= '(';
				}
				elseif( $condition === ')' ){
					// 空のwhere_open()～where_close()で'()'を生成するとエラーになるため、ダミーの式を入れる
					if( $last_condition === '(' ){
						$sql .= 'true';
					}

					$sql .= ')';
				}
				else{
					if( ! empty($sql) AND $last_condition !== '(' ){
						// Add the logic operator
						$sql .= ' ' . $logic . ' ';
					}

					// Split the condition
					list($column, $op, $value) = $condition;
					$op = trim($op);
					//Log::coredebug($column,$op,$value);

					if( $value === NULL ){
						if( $op === '=' ){
							// Convert "val = NULL" to "val IS NULL"
							$op = 'IS';
						}
						elseif( $op === '!=' || $op === '<>' ){
							// Convert "val != NULL" to "valu IS NOT NULL"
							$op = 'IS NOT';
						}
					}

					if( is_bool($value) ){
						if( $op === '=' ){
							$op = 'IS';
						}
						elseif( $op === '!=' || $op === '<>' ){
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

					// col in (select sql)に対応する
					if( $value instanceof Database_Query ){
						list($_insql_sql, $_insql_parameters) = $value->get_sql(true);
						// パラメータが$1から始まっているので、置き換える
//						Log::coredebug("_insql_sql={$_insql_sql} / _insql_parameters=",$_insql_parameters);
						$_insql_search = [];
						$_insql_replace = [];
						foreach($_insql_parameters as $_insql_parameter_index => $_insql_parameter_value){
							$_insql_search[] = '$' . ($_insql_parameter_index + 1);
							$_insql_replace[] = '$' . $this->parameter($_insql_parameter_value);
						}
						$_insql_search = array_reverse($_insql_search);
						$_insql_replace = array_reverse($_insql_replace);
						$_insql_sql = str_replace($_insql_search, $_insql_replace, $_insql_sql);
//						Log::coredebug($_insql_search,$_insql_replace);
//						Log::coredebug("replaced sql={$_insql_sql}");

						$value = DB::expr('(' . $_insql_sql . ')');
						$op    = 'IN';
					}

					if( $value instanceof Database_Expression ){
						$sql .= $column . ' ' . $op . ' ' . (string)$value;
					}
					else{
						if( $value === NULL ){
							$sql .= $column . ' ' . $op . ' NULL';
						}
						else{
							if( $value === TRUE ){
								$sql .= $column . ' ' . $op . ' TRUE';
							}
							else{
								if( $value === FALSE ){
									$sql .= $column . ' ' . $op . ' FALSE';
								}
								else{
									$sql .= $column . ' ' . $op . ' $' . $this->parameter($value);
								}
							}
						}
					}
				}

				$last_condition = $condition;
			}
		}

		//Log::coredebug("sql=$sql",$this->_parameters);
		return $sql;
	}

	function parameter($value)
	{
		if( is_array($value) ){
			foreach($value as $_value){
				$this->parameter($_value);
			}
		}
		else{
			$this->_parameters[$this->_parameters_index - 1] = $value;

			return $this->_parameters_index++;
		}
	}

	/**
	 * @return Database_Query
	 */
	function clear_parameter_index()
	{
		$this->_parameters_index = 1;

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function clear_parameter()
	{
		$this->_parameters       = [];
		$this->_parameters_index = 1;

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function clear_query_type()
	{
		$this->_query_type = NULL;

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function clear_select()
	{
		$this->_query_columns = [];

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function clear_from()
	{
		$this->_query_from = [];

		return $this;
	}

	/**
	 * SELECTクエリを作成
	 *
	 * @param array $columns
	 *
	 * @return Database_Query
	 */
	function select($columns = [])
	{
		if( ! is_array($columns) ){
			$columns = func_get_args();
		}
		$this->_query_type = 'SELECT';
		//$this->_query_columns = $columns;
		//$this->_query_columns = array_merge($this->_query_columns,$columns);
		foreach($columns as $col){
			$col = trim($col);
			if( ! in_array($col, $this->_query_columns) ){
				$this->_query_columns[] = $col;
			}
		}
		//Log::coredebug("select() _query_columns=",$columns,$this->_query_columns);
		//echo "<PRE>"; debug_print_backtrace(); echo "</PRE>";
		return $this;
	}

	/**
	 * UPDATEクエリを作成
	 *
	 * @param string $table テーブル名
	 *
	 * @return Database_Query
	 */
	function update($table)
	{
		$this->_query_type = 'UPDATE';
		$this->from($table);

		return $this;
	}

	/**
	 * INSERTクエリを作成
	 *
	 * @param string $table テーブル名
	 *
	 * @return Database_Query
	 */
	function insert($table)
	{
		$this->_query_type = 'INSERT';
		$this->from($table);

		return $this;
	}

	/**
	 * DELETEクエリを作成
	 *
	 * @return Database_Query
	 */
	function delete()
	{
		$this->_query_type = 'DELETE';

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function from($from)
	{
		$this->_query_from[] = $from;

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function set(array $values)
	{
		return $this->values($values);
	}

	/**
	 * @return Database_Query
	 */
	function with($with, $with_query = NULL)
	{
		if( is_array($with) ){
			$this->_query_with = array_merge($this->_query_with, $with);
		}
		else{
			$this->_query_with[] = ['name' => $with, 'query' => $with_query];
		}

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function values(array $values)
	{
		$this->_query_values = array_merge($this->_query_values, $values);

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	public function where()
	{
		return call_user_func_array([$this, 'and_where'], func_get_args());
	}

	/**
	 * @param mixed     $column
	 * @param mixed $op
	 * @param mixed $value
	 *
	 * @return Database_Query
	 */
	public function and_where($column, $op = null, $value = null)
	{
		if( $column instanceof \Closure ){
			$this->and_where_open();
			$column($this);
			$this->and_where_close();

			return $this;
		}
		if( is_array($column) ){
			foreach($column as $key => $val){
				if( is_array($val) ){
					$this->and_where($val[0], $val[1], $val[2]);
				}
				else{
					$this->and_where($key, '=', $val);
				}
			}
		}
		else{
			if( func_num_args() === 2 ){
				$value = $op;
				$op    = '=';
			}
			$this->_query_where[] = ['AND' => [$column, $op, $value]];
		}

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	public function or_where($column, $op = null, $value = null)
	{
		if( $column instanceof \Closure ){
			$this->or_where_open();
			$column($this);
			$this->or_where_close();

			return $this;
		}

		if( is_array($column) ){
			foreach($column as $key => $val){
				if( is_array($val) ){
					$this->or_where($val[0], $val[1], $val[2]);
				}
				else{
					$this->or_where($key, '=', $val);
				}
			}
		}
		else{
			if( func_num_args() === 2 ){
				$value = $op;
				$op    = '=';
			}
			$this->_query_where[] = ['OR' => [$column, $op, $value]];
		}

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	public function where_open()
	{
		return $this->and_where_open();
	}

	/**
	 * @return Database_Query
	 */
	public function and_where_open()
	{
		$this->_query_where[] = ['AND' => '('];

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	public function or_where_open()
	{
		$this->_query_where[] = ['OR' => '('];

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	public function where_close()
	{
		return $this->and_where_close();
	}

	/**
	 * @return Database_Query
	 */
	public function and_where_close()
	{
		$this->_query_where[] = ['AND' => ')'];

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	public function or_where_close()
	{
		$this->_query_where[] = ['OR' => ')'];

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function order_by($column, $direction = null)
	{
		//Log::coredebug("[database_query] order_by",$column,$direction);
		if( $column ){
			if( ! is_array($column) ){
				$this->order_by([(string)$column => $direction]);
			}
			else{
				foreach($column as $_column => $_direction){
					$this->_query_orderby[] = [$_column, $_direction];
				}
			}
		}

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function clear_order_by()
	{
		$this->_query_orderby = [];

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function returning($column)
	{
		if( is_array($column) ){
			$this->_query_returning = array_merge($this->_query_returning, $column);
		}
		else{
			$this->_query_returning[] = $column;
		}

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function clear_join()
	{
		$this->_query_join = [];

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function join($join_str)
	{
		$this->_query_join[] = $join_str;

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function limit($limit)
	{
		$this->_query_limit = (int)$limit;

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function offset($offset)
	{
		$this->_query_offset = (int)$offset;

		return $this;
	}

	/**
	 * @return Database_Query
	 */
	function distinct($flag)
	{
		$this->_query_distinct = (boolean)$flag;

		return $this;
	}
}
