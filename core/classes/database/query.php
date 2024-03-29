<?php
/**
 * Part of the mk1 framework.
 *
 * @package    mk1
 * @author     nmch
 * @license    MIT License
 */

class Database_Query
{
    protected $_sql;
    protected $_parameters = [];
    protected $_parameters_index = 1;
    protected $fetch_as = [];
    protected $affected_rows = null;
    //protected $result = NULL;

    protected $_query_type = null;
    protected $_query_columns = [];
    protected $_query_where = [];
    protected $_query_from = [];
    protected $_query_into = [];
    protected $_query_with = [];
    protected $_query_join = [];
    protected $_query_values = [];
    protected $_query_orderby = [];
    protected $_query_groupby = [];
    protected $_query_having = [];
    protected $_query_for = null;
    protected $_query_limit = null;
    protected $_query_offset = null;
    protected $_query_distinct = false;
    protected $_query_distinct_on = [];
    protected $_query_returning = [];
    protected $_query_primarykey = [];
    protected $_query_on_conflict = [];
    /** @var Database_Query */
    protected $_query_insert_query = null;

    protected $suppress_debug_log = false;

    protected $db;

    const ORDER_BY_INSERT_TOP = true;

    public function __construct($sql = null, $parameters = [])
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

    function set_db($db)
    {
        $this->db = $db;

        return $this;
    }

    /**
     * クエリ実行時のデバッグログ出力を抑制する
     * 主に巨大なサイズのパラメーターをとるクエリの実行用
     */
    function suppress_debug_log($flag = true): \Database_Query
    {
        $this->suppress_debug_log = $flag;

        return $this;
    }

    /**
     * クエリを実行する
     *
     * @param  null|Database_Connection  $db
     *
     * @return Database_Resultset
     * @throws MkException
     * @throws DatabaseQueryError
     */
    public function execute($db = null)
    {
        $db = $db ?: $this->db;
        if (!is_object($db)) {
            $db = Database_Connection::instance($db);
        }
        if ($this->_query_type) {
            $this->compile();
        }
        if (!$this->_sql) {
            throw new MkException('sql compile failed');
        }
        //Log::coredebug("[db] SQL = ".$this->_sql);
        try {
            /*
            $this->result = $db->query($this->_sql, $this->_parameters)->set_fetch_as($this->fetch_as);
            if($this->result instanceof Database_Resultset)
                $this->result->set_query($this);
            */
            $result = $db->query($this->_sql, $this->_parameters, $this->suppress_debug_log)->set_fetch_as($this->fetch_as);
            if ($result instanceof Database_Resultset) {
                $result->set_query($this);
                $this->affected_rows = $result->get_affected_rows();
            }

            return $result;
        } catch (Exception $e) {
            $message = $e->getMessage();
            //Log::error("Database_Query::execute() Exception : message={$message} / code={$e->getCode()} / prev_exception=",$e);
            $new_expception = new DatabaseQueryError($message, $e->getCode(), $e);
            // ここでERRORレベルでログを記録した場合、MUTEXのためのロック獲得エラー時に正常処理のなかでERRORログが残ってしまう
            //Log::error($message);
            throw $new_expception;
        }
    }

    /**
     * @return Database_Query
     * @throws MkException
     */
    public function compile()
    {
        if (empty($this->_query_type)) {
            throw new MkException('empty query type');
        }
        $this->clear_parameter_index();
        $this->_sql = $this->{'compile_'.$this->_query_type}();

        return $this;
    }

    /**
     * @return Database_Query
     */
    function clear_parameter_index()
    {
        $this->_parameters_index = 1;

        return $this;
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

    function get_fetch_as()
    {
        return $this->fetch_as;
    }

    /**
     * @param $fetch_as
     *
     * @return Database_Query
     */
    function set_fetch_as($fetch_as)
    {
        $this->fetch_as = $fetch_as;

        return $this;
    }

    public function compile_update()
    {
        $from = is_array($this->_query_from) ? reset($this->_query_from) : $this->_query_from;
        if (!$from) {
            throw new Exception('table required');
        }
        if (!$this->_query_values || !is_array($this->_query_values)) {
            throw new MkException('values required');
        }

        $sql = "UPDATE $from SET ";
        $ary = [];
        foreach ($this->_query_values as $key => $value) {
            if ($value instanceof Database_Expression) {
                $ary[] = "$key=".(string) $value;
            } else {
                $ary[] = "$key=$".$this->parameter($value);
            }
        }
        $sql .= implode(',', $ary);
        $where = $this->build_where();
        if ($where) {
            $sql .= " WHERE $where";
        }
        if ($this->_query_returning) {
            $sql .= " RETURNING ".(is_array($this->_query_returning) ? implode(',', $this->_query_returning) : $this->_query_returning);
        }

        return $sql;
    }

    function parameter($value)
    {
        if (is_array($value)) {
            $indexes = [];
            foreach ($value as $_value) {
                $indexes[] = $this->parameter($_value);
            }

            return $indexes;
        } else {
            $this->_parameters[$this->_parameters_index - 1] = $value;

            return $this->_parameters_index++;
        }
    }

    function build_where()
    {
        //		Log::coredebug($this->_query_where,$this->_query_values);
        $last_condition = null;

        $sql = '';
        foreach ($this->_query_where as $group) {
            // Process groups of conditions
            foreach ($group as $logic => $condition) {
                //Log::coredebug("$logic = ",$condition);
                //echo "[$logic = "; var_export($condition); echo "]\n";
                if ($condition === '(') {
                    if (!empty($sql) and $last_condition !== '(') {
                        // Include logic operator
                        $sql .= ' '.$logic.' ';
                    }

                    $sql .= '(';
                } elseif ($condition === ')') {
                    // 空のwhere_open()～where_close()で'()'を生成するとエラーになるため、ダミーの式を入れる
                    if ($last_condition === '(') {
                        $sql .= 'true';
                    }

                    $sql .= ')';
                } elseif (($exp = Arr::get($condition, 0)) instanceof Database_Expression) {
                    $sql .= ' '.(string) $exp;
                } else {
                    if (!empty($sql) and $last_condition !== '(') {
                        // Add the logic operator
                        $sql .= ' '.$logic.' ';
                    }

                    // Split the condition
                    [$column, $op, $value] = $condition;
                    $op = trim($op);
                    //Log::coredebug($column,$op,$value);

                    if ($value === null) {
                        if ($op === '=') {
                            // Convert "val = NULL" to "val IS NULL"
                            $op = 'IS';
                        } elseif ($op === '!=' || $op === '<>') {
                            // Convert "val != NULL" to "valu IS NOT NULL"
                            $op = 'IS NOT';
                        }
                    }

                    if (is_bool($value)) {
                        if ($op === '=') {
                            $op = 'IS';
                        } elseif ($op === '!=' || $op === '<>') {
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
                    if ($value instanceof Database_Query) {
                        [$_insql_sql, $_insql_parameters] = $value->get_sql(true);
                        // パラメータが$1から始まっているので、置き換える
                        //						Log::coredebug("_insql_sql={$_insql_sql} / _insql_parameters=",$_insql_parameters);
                        $_insql_search = [];
                        $_insql_replace = [];
                        foreach ($_insql_parameters as $_insql_parameter_index => $_insql_parameter_value) {
                            $_insql_search[] = '/\$'.($_insql_parameter_index + 1).'\b/';
                            $_insql_replace[] = '\$'.$this->parameter($_insql_parameter_value);
                        }
                        $_insql_search = array_reverse($_insql_search);
                        $_insql_replace = array_reverse($_insql_replace);
                        $_insql_sql = preg_replace($_insql_search, $_insql_replace, $_insql_sql);
                        //						Log::coredebug($_insql_search,$_insql_replace);
                        //						Log::coredebug("replaced sql={$_insql_sql}");

                        $value = DB::expr('('.$_insql_sql.')');
                        $op = $op === '=' ? 'IN' : $op;
                    }

                    if ($value instanceof Database_Expression) {
                        $exp = $column.' '.$op.' '.strval($value);
                    } else {
                        if ($value === null) {
                            $exp = $column.' '.$op.' NULL';
                        } else {
                            if ($value === true) {
                                $exp = $column.' '.$op.' TRUE';
                            } else {
                                if ($value === false) {
                                    $exp = $column.' '.$op.' FALSE';
                                } else {
                                    $exp = $column.' '.$op.' $'.$this->parameter($value);
                                }
                            }
                        }
                    }
                    if ($op === '#') {
                        // ビット演算子を利用する場合は式を括弧で囲む
                        $exp = "({$exp})";
                    }
                    $sql .= $exp;
                }

                $last_condition = $condition;
            }
        }

        //Log::coredebug("sql=$sql",$this->_parameters);
        return $sql;
    }

    public function get_sql($with_parameters = false)
    {
        if ($this->_query_type) {
            $this->compile();
        }

        if ($with_parameters) {
            return [$this->_sql, $this->_parameters];
        } else {
            return $this->_sql;
        }
    }

    /**
     * @param $sql
     *
     * @return Database_Query
     * @throws MkException
     */
    public function set_sql($sql)
    {
        if (!is_string($sql)) {
            throw new MkException('sql must be a string');
        }
        $this->_sql = $sql;

        return $this;
    }

    public function compile_insert()
    {
        // intoが設定されている場合は優先
        $table = $this->_query_into;
        // intoが無い場合はfromを設定
        $table = $table ?: (is_array($this->_query_from) ? reset($this->_query_from) : $this->_query_from);
        if (!$table) {
            throw new Exception('table required');
        }

        $sql = "INSERT INTO {$table} ";
        /**
         * カラムも値も定義されていない場合はDEFAULT VALUESを使う
         */
        if (!count($this->_query_columns) && !count($this->_query_values)) {
            $sql .= "DEFAULT VALUES";
        } else {
            /**
             * カラム
             */
            if (count($this->_query_columns)) {
                $sql .= '('.implode(',', $this->_query_columns).')';
                // カラムが定義されているときはINSERT...SELECTになる必要があるので
                // _query_insert_selectが存在しない場合はエラーにする
                if (!$this->_query_insert_query || !($this->_query_insert_query instanceof Database_Query)) {
                    throw new MkException("INSERT用のクエリが設定されていません");
                }
            } elseif (count($this->_query_values)) {
                $sql .= '('.implode(',', array_keys($this->_query_values)).')';
            } else {
                throw new MkException("empty columns");
            }

            /**
             * 値(VALUES)
             */
            if ($this->_query_values) {
                //$sql .= '(' . implode(',', array_keys($this->_query_values)) . ')';
                $ary = [];
                //			Log::coredebug($this->_query_values);
                foreach ($this->_query_values as $value) {
                    if ($value instanceof Database_Expression) {
                        $ary[] = strval($value);
                    } else {
                        $ary[] = '$'.$this->parameter($value);
                    }
                }
                $sql .= ' VALUES ('.implode(',', $ary).')';
            }

            /**
             * 値をクエリで指定
             */
            if ($this->_query_insert_query && $this->_query_insert_query instanceof Database_Query) {
                $insert_select_sql = $this->_query_insert_query->get_sql();
                $sql .= (' '.$insert_select_sql);
            }
        }

        // fixme これ指定されるとクエリエラーになるのでは。。。
        $where = $this->build_where();
        if ($where) {
            $sql .= " WHERE $where";
        }

        if ($this->_query_on_conflict) {
            $sql .= " ON CONFLICT";
            if ($conflict_columns = Arr::get($this->_query_on_conflict, 'columns')) {
                $sql .= (" (".implode(',', $conflict_columns).")");
            }
            if ($on = Arr::get($this->_query_on_conflict, 'on')) {
                if ($on === 'constraint') {
                    $constraint = Arr::get($this->_query_on_conflict, 'constraint');
                    $sql .= " ON CONSTRAINT {$constraint}";
                }
            }

            $do = Arr::get($this->_query_on_conflict, 'do');
            if ($do === 'update') {
                $do_values = [];
                if ($this->_query_columns) {
                    foreach ($this->_query_columns as $key) {
                        if (isset($conflict_columns) && is_array($conflict_columns) && in_array($key, $conflict_columns)) {
                            // on conflictで制約の推定を使っている場合、推定に使われたキーは更新しない
                            continue;
                        }
                        $do_values[] = "{$key}=EXCLUDED.{$key}";
                    }
                }
                if ($this->_query_values) {
                    foreach ($this->_query_values as $key => $value) {
                        if (isset($conflict_columns) && is_array($conflict_columns) && in_array($key, $conflict_columns)) {
                            // on conflictで制約の推定を使っている場合、推定に使われたキーは更新しない
                            continue;
                        }
                        $do_values[] = "{$key}=EXCLUDED.{$key}";
                    }
                }
                if ($do_values) {
                    $sql .= " DO UPDATE SET ";
                    $sql .= implode(',', $do_values);
                }
            } elseif ($do === 'nothing') {
                $sql .= " DO NOTHING ";
            }
        }

        if ($this->_query_returning) {
            $sql .= " RETURNING ".(is_array($this->_query_returning) ? implode(',', $this->_query_returning) : $this->_query_returning);
        }

        return $sql;
    }

    public function compile_select()
    {
        //Log::coredebug("_query_columns=",$this->_query_columns);
        $sql = '';
        if ($this->_query_with) {
            $sql_with = [];
            foreach ($this->_query_with as $with) {
                $sql_with[] = Arr::get($with, 'name').' AS ('.Arr::get($with, 'query').')';
            }
            $sql .= "WITH ".implode(',', $sql_with)." ";
        }
        $sql .= "SELECT ";
        if ($this->_query_distinct) {
            $sql .= " DISTINCT ";
        } elseif ($this->_query_distinct_on) {
            $sql .= " DISTINCT ON (".implode(',', $this->_query_distinct_on).")";
        }
        $sql .= $this->_query_columns ? implode(',', $this->_query_columns) : '*';
        if ($this->_query_into) {
            $sql .= " INTO {$this->_query_into} ";
        }
        if ($this->_query_from) {
            $sql .= " FROM ".implode(',', $this->_query_from);
        }
        $where = $this->build_where();
        if ($this->_query_join) {
            $sql .= ' '.implode(' ', $this->_query_join);
        }
        if ($where) {
            $sql .= " WHERE $where";
        }
        if ($this->_query_groupby) {
            $sql .= " GROUP BY ".implode(',', $this->_query_groupby);
        }
        if ($this->_query_having) {
            $sql .= " HAVING ".$this->_query_having;
        }
        if ($this->_query_orderby) {
            $sql .= " ORDER BY ".implode(',', array_map(function ($ary) {
                        return implode(' ', $ary);
                    }, $this->_query_orderby
                    )
                );
        }
        if ($this->_query_limit) {
            $sql .= " LIMIT $this->_query_limit";
        }
        if ($this->_query_offset) {
            $sql .= " OFFSET $this->_query_offset";
        }
        if ($this->_query_for) {
            $sql .= " FOR {$this->_query_for}";
        }

        //Log::coredebug("[db query] sql=$sql");
        return $sql;
    }

    public function compile_delete()
    {
        $sql = "DELETE ";
        $sql .= " FROM ".implode(',', $this->_query_from);
        $where = $this->build_where();
        if ($where) {
            $sql .= " WHERE $where";
        }
        if ($this->_query_limit) {
            $sql .= " LIMIT $this->_query_limit";
        }
        if ($this->_query_returning) {
            $sql .= " RETURNING ".(is_array($this->_query_returning) ? implode(',', $this->_query_returning) : $this->_query_returning);
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
        return (!empty($this->_query_where));
    }

    /**
     * @return Database_Query
     */
    function clear_parameter()
    {
        $this->_parameters = [];
        $this->_parameters_index = 1;

        return $this;
    }

    /**
     * @return Database_Query
     */
    function clear_query_type()
    {
        $this->_query_type = null;

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

    function clear_offset()
    {
        $this->_query_offset = null;

        return $this;
    }

    function clear_limit()
    {
        $this->_query_limit = null;

        return $this;
    }

    /**
     * @return Database_Query
     */
    function clear_into()
    {
        $this->_query_into = [];

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
     * INSERTクエリ用のデータ取得クエリを設定
     *
     * @param  Database_Query  $query
     *
     * @return Database_Query
     */
    function insert_query(Database_Query $query): Database_Query
    {
        $this->_query_insert_query = $query;

        return $this;
    }

    /**
     * SELECTクエリを作成
     *
     * @param  array  $columns
     * @param  bool  $permit_overlap_column
     *
     * @return Database_Query
     */
    function select($columns = [], $permit_overlap_column = false)
    {
        if (!is_array($columns)) {
            $columns = func_get_args();
        }
        $this->_query_type = 'SELECT';
        //$this->_query_columns = $columns;
        //$this->_query_columns = array_merge($this->_query_columns,$columns);
        foreach ($columns as $col) {
            $col = trim($col);
            if ($permit_overlap_column || !in_array($col, $this->_query_columns)) {
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
     * @param  string  $table  テーブル名
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
     * @param  string  $from
     *
     * @return Database_Query
     */
    function from($from)
    {
        $this->_query_from[] = $from;

        return $this;
    }

    /**
     * @param  string  $for
     *
     * @return Database_Query
     */
    function select_for($for)
    {
        $this->_query_for = $for;

        return $this;
    }

    /**
     * @param $into
     *
     * @return Database_Query
     */
    function into($into)
    {
        $this->_query_into = $into;

        return $this;
    }

    /**
     * INSERTクエリを作成
     *
     * @param  string  $table  テーブル名
     * @param  array  $columns  カラム名の配列
     *
     * @return Database_Query
     */
    function insert($table = null, array $columns = [])
    {
        $this->_query_type = 'INSERT';
        $this->into($table);

        if (count($columns)) {
            $this->_query_columns = $columns;
        }

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
     * @param  array  $values
     *
     * @return Database_Query
     */
    function set(array $values)
    {
        return $this->values($values);
    }

    /**
     * @param  array  $values
     *
     * @return Database_Query
     */
    function values(array $values)
    {
        $this->_query_values = array_merge($this->_query_values, $values);

        return $this;
    }

    /**
     * @param  string  $with
     * @param  string  $with_query
     *
     * @return Database_Query
     */
    function with($with, $with_query = null)
    {
        if (is_array($with)) {
            $this->_query_with = array_merge($this->_query_with, $with);
        } else {
            $this->_query_with[] = ['name' => $with, 'query' => $with_query];
        }

        return $this;
    }

    public function having($exp)
    {
        $this->_query_having = $exp;
    }

    /**
     * @return Database_Query
     */
    public function where()
    {
        return call_user_func_array([$this, 'and_where'], func_get_args());
    }

    /**
     * @param  mixed  $column
     * @param  mixed  $op
     * @param  mixed  $value
     *
     * @return Database_Query
     */
    public function and_where($column, $op = null, $value = null)
    {
        if ($column instanceof \Closure) {
            $this->and_where_open();
            $column($this);
            $this->and_where_close();

            return $this;
        }
        if (is_array($column)) {
            foreach ($column as $key => $val) {
                if (is_array($val)) {
                    $this->and_where($val[0], $val[1], $val[2]);
                } else {
                    $this->and_where($key, '=', $val);
                }
            }
        } else {
            if (func_num_args() === 2) {
                $value = $op;
                $op = '=';
            }
            $this->_query_where[] = ['AND' => [$column, $op, $value]];
        }

        return $this;
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
    public function and_where_close()
    {
        $this->_query_where[] = ['AND' => ')'];

        return $this;
    }

    /**
     * @param  string  $column
     * @param  string  $op
     * @param  mixed  $value
     *
     * @return Database_Query
     */
    public function or_where($column, $op = null, $value = null)
    {
        if ($column instanceof \Closure) {
            $this->or_where_open();
            $column($this);
            $this->or_where_close();

            return $this;
        }

        if (is_array($column)) {
            foreach ($column as $key => $val) {
                if (is_array($val)) {
                    $this->or_where($val[0], $val[1], $val[2]);
                } else {
                    $this->or_where($key, '=', $val);
                }
            }
        } else {
            if (func_num_args() === 2) {
                $value = $op;
                $op = '=';
            }
            $this->_query_where[] = ['OR' => [$column, $op, $value]];
        }

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
    public function or_where_close()
    {
        $this->_query_where[] = ['OR' => ')'];

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
    public function where_close()
    {
        return $this->and_where_close();
    }

    /**
     * @param  string  $column
     * @param  string  $direction
     *
     * @return Database_Query
     */
    function order_by($column, $direction = null, $insert_top = false)
    {
        //Log::coredebug("[database_query] order_by",$column,$direction);
        if ($column) {
            if (!is_array($column)) {
                $this->order_by([(string) $column => $direction], null, $insert_top);
            } else {
                foreach ($column as $_column => $_direction) {
                    if ($insert_top) {
                        array_unshift($this->_query_orderby, [$_column, $_direction]);
                    } else {
                        $this->_query_orderby[] = [$_column, $_direction];
                    }
                }
            }
        }

        return $this;
    }

    function overwrite_order_by(array $order_by_array)
    {
        $this->_query_orderby = $order_by_array;

        return $this;
    }

    /**
     * @param  string  $column
     *
     * @return Database_Query
     */
    function group_by($column)
    {
        if ($column) {
            if (!is_array($column)) {
                $this->group_by([(string) $column]);
            } else {
                foreach ($column as $_column) {
                    $this->_query_groupby[] = $_column;
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

    function get_order_by(): array
    {
        return $this->_query_orderby ?: [];
    }

    /**
     * @param  string|array  $column
     *
     * @return Database_Query
     */
    function returning($column)
    {
        if (is_array($column)) {
            $this->_query_returning = array_merge($this->_query_returning, $column);
        } else {
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
     * @param  string  $join_str
     *
     * @return Database_Query
     * @see Model_Query::apply_joins()
     */
    function join($join_str, $prepend = false)
    {
        if ($prepend) {
            array_unshift($this->_query_join, $join_str);
        } else {
            $this->_query_join[] = $join_str;
        }

        return $this;
    }

    /**
     * @param  integer  $limit
     *
     * @return Database_Query
     */
    function limit($limit)
    {
        $this->_query_limit = (int) $limit;

        return $this;
    }

    /**
     * @param  string  $offset
     *
     * @return Database_Query
     */
    function offset($offset)
    {
        $this->_query_offset = (int) $offset;

        return $this;
    }

    /**
     * @param  boolean  $flag
     *
     * @return Database_Query
     */
    function distinct($flag)
    {
        $this->_query_distinct = (boolean) $flag;

        return $this;
    }

    /**
     * @return Database_Query
     */
    function distinct_on($columns)
    {
        if (!is_array($columns)) {
            $columns = [$columns];
        }
        $this->_query_distinct_on = array_merge($this->_query_distinct_on, $columns);

        return $this;
    }

    function on_conflict(array $columns, $do = 'update')
    {
        $this->_query_on_conflict = [
            'columns' => $columns,
            'do' => $do,
        ];

        return $this;
    }

    function on_conflict_on_constraint($constraint, $do = 'update')
    {
        $this->_query_on_conflict = [
            'on' => 'constraint',
            'constraint' => $constraint,
            'do' => $do,
        ];

        return $this;
    }
}
